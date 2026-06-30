<?php

declare(strict_types=1);

namespace App\Service\Custody\Holidays;

/**
 * Période de vacances normalisée renvoyée par {@see SchoolHolidaysProvider} (dates calendaires
 * déjà converties en Europe/Paris). Découple le moteur de la forme de la source officielle.
 */
final readonly class ImportedHoliday
{
    public function __construct(
        public string $label,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public string $zone,
        public string $academy,
        public string $schoolYear,
    ) {
    }
}
