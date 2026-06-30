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
            $item->expiresAfter(self::CACHE_TTL);

            return $this->doFetch($zone, $academy, $schoolYear);
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
        $start = $this->toLocalDate($rec['start_date'] ?? null, $tz);
        $end = $this->toLocalDate($rec['end_date'] ?? null, $tz);
        if (null === $start || null === $end || $end < $start) {
            $this->logger->warning('Import vacances : enregistrement aux bornes invalides ignoré.', ['record' => $rec]);

            return null;
        }

        return new ImportedHoliday(
            label: (string) ($rec['description'] ?? 'Vacances'),
            startDate: $start,
            endDate: $end,
            zone: $zone,
            academy: $location ?: $academy,
            schoolYear: $schoolYear,
        );
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
