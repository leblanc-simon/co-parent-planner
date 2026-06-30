<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Custody\Holidays\ImportedHoliday;
use App\Service\Custody\Holidays\SchoolHolidaysProvider;

/**
 * Double de test du provider de vacances (aucun appel réseau en CI). Renvoie des fixtures fixes.
 */
final class FakeSchoolHolidaysProvider implements SchoolHolidaysProvider
{
    public function fetch(string $zone, string $academy, string $schoolYear): array
    {
        return [
            new ImportedHoliday(
                label: 'Vacances de la Toussaint',
                startDate: new \DateTimeImmutable('2026-10-17'),
                endDate: new \DateTimeImmutable('2026-11-02'),
                zone: $zone,
                academy: $academy,
                schoolYear: $schoolYear,
            ),
            new ImportedHoliday(
                label: 'Vacances de Noël',
                startDate: new \DateTimeImmutable('2026-12-19'),
                endDate: new \DateTimeImmutable('2027-01-04'),
                zone: $zone,
                academy: $academy,
                schoolYear: $schoolYear,
            ),
        ];
    }
}
