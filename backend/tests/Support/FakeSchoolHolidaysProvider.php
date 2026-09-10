<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Service\Custody\Holidays\ImportedHoliday;
use App\Service\Custody\Holidays\SchoolHolidaysProvider;

/**
 * Double de test du provider de vacances (aucun appel réseau en CI).
 *
 * Ne publie que l'année scolaire {@see self::PUBLISHED} — comme la source réelle, qui ne connaît
 * pas encore les années suivantes. L'import balaie plusieurs années de l'horizon : un double qui
 * renverrait les mêmes fixtures pour toutes créerait autant de jeux de périodes que d'années.
 */
final class FakeSchoolHolidaysProvider implements SchoolHolidaysProvider
{
    public const string PUBLISHED = '2026-2027';

    public function fetch(string $zone, string $academy, string $schoolYear): array
    {
        if (self::PUBLISHED !== $schoolYear) {
            return [];
        }

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
