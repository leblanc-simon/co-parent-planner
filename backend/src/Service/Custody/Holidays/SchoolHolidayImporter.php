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
 */
final class SchoolHolidayImporter
{
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
        $year = $schoolYear ?? $this->currentSchoolYear();

        $count = 0;
        foreach ($this->provider->fetch($zone, $academy, $year) as $imported) {
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
            }
            ++$count;
        }
        $this->em->flush();

        return $count;
    }

    /** Année scolaire courante « YYYY-YYYY » (bascule en septembre). */
    public function currentSchoolYear(): string
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'));
        $year = (int) $now->format('Y');
        $start = (int) $now->format('n') >= 9 ? $year : $year - 1;

        return \sprintf('%d-%d', $start, $start + 1);
    }
}
