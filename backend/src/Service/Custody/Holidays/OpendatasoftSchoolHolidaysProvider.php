<?php

declare(strict_types=1);

namespace App\Service\Custody\Holidays;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Implémentation réelle de {@see SchoolHolidaysProvider} via l'API Opendatasoft Explore v2.1
 * (dataset `fr-en-calendrier-scolaire`) — schéma vérifié §3.10.
 *
 * - Filtre la population « élèves » (`Élèves` ou `-`), ignore `Enseignants`.
 * - Convertit les bornes **UTC → Europe/Paris** puis dérive la date calendaire (sinon décalage d'un jour).
 * - Met les réponses en cache (TTL) ; pagination `limit`/`offset`.
 */
final class OpendatasoftSchoolHolidaysProvider implements SchoolHolidaysProvider
{
    private const string DATASET_URL = 'https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records';
    private const int PAGE = 100;
    private const int CACHE_TTL = 604800; // 7 jours
    private const int CACHE_TTL_EMPTY = 86400; // 1 jour : année pas encore publiée, on retente vite
    /** Préfixe des marqueurs de début de grandes vacances non encore bornées (§3.10). */
    private const string SUMMER_MARKER_PREFIX = 'Début des ';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::SCHOOL_HOLIDAYS_URL)%')]
        private readonly ?string $datasetUrl = null,
    ) {
    }

    public function fetch(string $zone, string $academy, string $schoolYear): array
    {
        $key = 'holidays_'.md5($zone.'|'.$academy.'|'.$schoolYear);

        return $this->cache->get($key, function (ItemInterface $item) use ($zone, $academy, $schoolYear): array {
            $holidays = $this->doFetch($zone, $academy, $schoolYear);
            // Une année scolaire encore non publiée renvoie une liste vide : la mémoriser une
            // semaine retarderait d'autant l'apparition du calendrier à venir.
            $item->expiresAfter([] === $holidays ? self::CACHE_TTL_EMPTY : self::CACHE_TTL);

            return $holidays;
        });
    }

    /**
     * @return list<ImportedHoliday>
     */
    private function doFetch(string $zone, string $academy, string $schoolYear): array
    {
        $url = $this->datasetUrl ?: self::DATASET_URL;
        $tz = new \DateTimeZone('Europe/Paris');
        $result = [];
        $offset = 0;

        do {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'query' => [
                        'where' => \sprintf('annee_scolaire="%s"', $schoolYear),
                        'refine' => \sprintf('zones:"Zone %s"', $zone),
                        'limit' => self::PAGE,
                        'offset' => $offset,
                    ],
                    'timeout' => 15,
                ]);
                $data = $response->toArray();
            } catch (\Throwable $e) {
                $this->logger->error('Import vacances : source indisponible.', ['exception' => $e]);
                throw new \RuntimeException('Source des vacances scolaires indisponible.', 0, $e);
            }

            /** @var list<array<string, mixed>> $records */
            $records = $data['results'] ?? [];
            foreach ($records as $rec) {
                $holiday = $this->mapRecord($rec, $zone, $academy, $schoolYear, $tz);
                if (null !== $holiday) {
                    $result[] = $holiday;
                }
            }

            $total = (int) ($data['total_count'] ?? 0);
            $offset += self::PAGE;
        } while ($offset < $total && [] !== $records);

        return $result;
    }

    /**
     * @param array<string, mixed> $rec
     */
    private function mapRecord(array $rec, string $zone, string $academy, string $schoolYear, \DateTimeZone $tz): ?ImportedHoliday
    {
        // Population : ne garder que les périodes pertinentes aux élèves.
        $population = (string) ($rec['population'] ?? '-');
        if (!\in_array($population, ['-', 'Élèves'], true)) {
            return null;
        }
        // Filtrage par académie (location), insensible à la casse/espaces.
        $location = trim((string) ($rec['location'] ?? ''));
        if ('' !== $academy && 0 !== strcasecmp($location, trim($academy))) {
            return null;
        }
        $label = (string) ($rec['description'] ?? 'Vacances');
        $start = $this->toLocalDate($rec['start_date'] ?? null, $tz);
        $bound = $this->toLocalDate($rec['end_date'] ?? null, $tz);

        // Marqueur ponctuel (`start == end`) : la source publie un POINT, pas une période. C'est sa
        // façon d'annoncer le début des grandes vacances d'une année scolaire dont la fin — la
        // rentrée suivante — n'est pas encore arrêtée (« Début des Vacances d'Été »).
        if (null !== $start && $bound == $start) {
            $summer = $this->provisionalSummer($label, $start);
            if (null === $summer) {
                // Autre marqueur non borné (un « Pont de l'Ascension » encore provisoire, par
                // exemple) : la source le précisera, on n'invente pas de durée.
                $this->logger->info('Import vacances : marqueur non borné par la source, ignoré.', ['record' => $rec]);

                return null;
            }
            [$label, $end] = $summer;
        } else {
            // Dans le dataset, `end_date` encode minuit (Europe/Paris) du JOUR DE RENTRÉE — une borne
            // de fin EXCLUSIVE. Le dernier jour de vacances effectif est donc la veille.
            $end = $bound?->modify('-1 day');
        }

        if (null === $start || null === $end || $end < $start) {
            $this->logger->warning('Import vacances : enregistrement aux bornes invalides ignoré.', ['record' => $rec]);

            return null;
        }

        return new ImportedHoliday(
            label: $label,
            startDate: $start,
            endDate: $end,
            zone: $zone,
            academy: $location ?: $academy,
            schoolYear: $schoolYear,
        );
    }

    /**
     * Grandes vacances annoncées mais pas encore bornées : libellé définitif + fin **provisoire**.
     *
     * Tant que la rentrée suivante n'est pas fixée, la source ne publie que « Début des Vacances
     * d'Été » — l'été, soit la période la plus longue à répartir entre deux parents, resterait
     * absent du planning pendant des mois. On la matérialise donc jusqu'au 31 août, dernier jour de
     * vacances en métropole à un jour près (la rentrée y tombe le 1er ou le 2 septembre).
     *
     * Le libellé est **normalisé** vers celui que la source emploiera une fois l'année complète
     * (« Début des Vacances d'Été » → « Vacances d'Été ») : la clé naturelle d'upsert (label +
     * start_date) coïncidera alors, la vraie fin remplacera la provisoire, et la règle de garde
     * attachée à la période survivra — là où une période au libellé différent aurait été balayée
     * comme obsolète, sa règle avec elle.
     *
     * Renvoie `null` hors de ce cas : un début d'été austral (Réunion, Nouvelle-Calédonie) ou tout
     * autre marqueur ne se déduit pas ainsi. Le périmètre applicatif (zones A/B/C) est métropolitain,
     * la garde reste explicite.
     *
     * @return array{string, \DateTimeImmutable}|null [libellé normalisé, fin provisoire]
     */
    private function provisionalSummer(string $label, \DateTimeImmutable $start): ?array
    {
        if (!str_starts_with($label, self::SUMMER_MARKER_PREFIX)) {
            return null;
        }
        // Grandes vacances de l'hémisphère nord : elles commencent fin juin ou début juillet.
        $month = (int) $start->format('n');
        if (6 !== $month && 7 !== $month) {
            return null;
        }

        return [
            substr($label, \strlen(self::SUMMER_MARKER_PREFIX)),
            $start->setDate((int) $start->format('Y'), 8, 31),
        ];
    }

    /** Convertit une date ISO UTC en date calendaire Europe/Paris (00:00). */
    private function toLocalDate(mixed $raw, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        try {
            $instant = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $instant->setTimezone($tz)->format('Y-m-d'), $tz) ?: null;
    }
}
