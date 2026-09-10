<?php

declare(strict_types=1);

namespace App\Service\Custody\Holidays;

use App\Entity\Family;
use App\Entity\SchoolHoliday;
use App\Enum\HolidaySource;
use App\Repository\SchoolHolidayRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Importe/met à jour les périodes officielles d'une famille (Lot 3 §3.10) de façon **idempotente**
 * (upsert sur clé naturelle). Les périodes `manual` et les `HolidayRule` existantes sont préservées.
 *
 * Sans année explicite, l'import couvre **toutes les années scolaires traversées par l'horizon
 * glissant de garde** (≈18 mois), pas seulement l'année en cours : sinon un import lancé en été ne
 * ramène que l'année scolaire qui s'achève, et l'année à venir n'arrive qu'en septembre — trop tard
 * pour paramétrer les règles de vacances sur l'horizon.
 */
final class SchoolHolidayImporter
{
    /** Aligné sur l'horizon de {@see \App\Service\Custody\CustodyGenerator} (§3.9). */
    private const int HORIZON_MONTHS = 18;

    public function __construct(
        private readonly SchoolHolidaysProvider $provider,
        private readonly SchoolHolidayRepository $holidays,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return int nombre de périodes importées/mises à jour */
    public function import(Family $family, ?string $schoolYear = null): int
    {
        $zone = $family->getSchoolZone();
        $academy = $family->getAcademy();
        if (null === $zone || null === $academy || '' === $zone || '' === $academy) {
            return 0; // garde-fou ; l'endpoint valide en amont (422)
        }
        $years = null !== $schoolYear && '' !== $schoolYear ? [$schoolYear] : $this->schoolYearsInHorizon();

        $count = 0;
        foreach ($years as $year) {
            $count += $this->importYear($family, $zone, $academy, $year);
        }

        return $count;
    }

    /** Années scolaires « YYYY-YYYY » traversées par l'horizon glissant, de la plus proche à la plus lointaine.
     *
     * @return list<string>
     */
    public function schoolYearsInHorizon(): array
    {
        $today = $this->today();
        $first = $this->schoolYearStart($today);
        $last = $this->schoolYearStart($today->modify('+'.self::HORIZON_MONTHS.' months'));

        $years = [];
        for ($start = $first; $start <= $last; ++$start) {
            $years[] = \sprintf('%d-%d', $start, $start + 1);
        }

        return $years;
    }

    /** Année scolaire courante « YYYY-YYYY » (bascule en septembre). */
    public function currentSchoolYear(): string
    {
        $start = $this->schoolYearStart($this->today());

        return \sprintf('%d-%d', $start, $start + 1);
    }

    /** Import d'une année scolaire ; @return int périodes importées/mises à jour */
    private function importYear(Family $family, string $zone, string $academy, string $year): int
    {
        $fetched = $this->provider->fetch($zone, $academy, $year);
        if ([] === $fetched) {
            // Année pas (encore) publiée par la source : ne rien balayer, sinon un calendrier déjà
            // importé — et ses règles — disparaîtrait sur une réponse vide.
            return 0;
        }

        // Balayage idempotent : on part des périodes officielles déjà en base pour l'année, et on
        // supprime en fin celles que la source ne renvoie plus (disparues ou dont le start_date —
        // partie de la clé naturelle — a été rectifié, ce qui créerait sinon un doublon orphelin).
        $stale = [];
        foreach ($this->holidays->findOfficialBySchoolYear($family, $year) as $h) {
            $stale[$h->getId()->toRfc4122()] = $h;
        }

        $count = 0;
        foreach ($fetched as $imported) {
            $existing = $this->holidays->findOneByNaturalKey(
                $family,
                $imported->zone,
                $imported->academy,
                $imported->schoolYear,
                $imported->label,
                $imported->startDate,
            );
            $holiday = $existing ?? (new SchoolHoliday())
                ->setFamily($family)
                ->setLabel($imported->label)
                ->setStartDate($imported->startDate)
                ->setZone($imported->zone)
                ->setAcademy($imported->academy)
                ->setSchoolYear($imported->schoolYear)
                ->setSource(HolidaySource::Official);

            // Bornes peuvent évoluer côté source (la fin surtout) ; on met à jour sans toucher la règle.
            $holiday->setEndDate($imported->endDate);
            if (null === $existing) {
                $this->em->persist($holiday);
            } else {
                unset($stale[$existing->getId()->toRfc4122()]); // revue → conservée
            }
            ++$count;
        }

        // Périodes officielles non revues → obsolètes : suppression (leur règle suit via cascade).
        foreach ($stale as $obsolete) {
            $this->em->remove($obsolete);
        }
        $this->em->flush();

        return $count;
    }

    /** Année de début de l'année scolaire contenant $date (bascule en septembre). */
    private function schoolYearStart(\DateTimeImmutable $date): int
    {
        $year = (int) $date->format('Y');

        return (int) $date->format('n') >= 9 ? $year : $year - 1;
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'));
    }
}
