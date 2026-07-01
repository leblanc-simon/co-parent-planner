<?php

declare(strict_types=1);

namespace App\Tests\Integration\Custody;

use App\Entity\Family;
use App\Enum\FamilyType;
use App\Repository\SchoolHolidayRepository;
use App\Service\Custody\Holidays\ImportedHoliday;
use App\Service\Custody\Holidays\SchoolHolidayImporter;
use App\Service\Custody\Holidays\SchoolHolidaysProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Import des vacances (§3.10) : idempotence et **balayage** des périodes officielles disparues /
 * rectifiées côté source (P2-B) — pas de doublon orphelin quand un start_date change.
 */
final class SchoolHolidayImporterTest extends KernelTestCase
{
    private function importerWith(SchoolHolidaysProvider $provider): SchoolHolidayImporter
    {
        $c = self::getContainer();
        $repo = $c->get(SchoolHolidayRepository::class);
        $em = $c->get(EntityManagerInterface::class);
        $clock = $c->get(ClockInterface::class);
        self::assertInstanceOf(SchoolHolidayRepository::class, $repo);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(ClockInterface::class, $clock);

        return new SchoolHolidayImporter($provider, $repo, $em, $clock);
    }

    public function testImportIsIdempotentAndSweepsRectifiedPeriods(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = self::getContainer()->get(SchoolHolidayRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(SchoolHolidayRepository::class, $repo);

        $family = (new Family())->setName('Import')->setType(FamilyType::Separated)
            ->setSchoolZone('A')->setAcademy('Bordeaux');
        $em->persist($family);
        $em->flush();

        // Provider scripté : une file de résultats, un par appel de fetch().
        $provider = new class implements SchoolHolidaysProvider {
            /** @var list<list<ImportedHoliday>> */
            public array $queue = [];

            public function fetch(string $zone, string $academy, string $schoolYear): array
            {
                return array_shift($this->queue) ?? [];
            }
        };
        $toussaint = static fn (string $start) => new ImportedHoliday(
            label: 'Vacances de la Toussaint',
            startDate: new \DateTimeImmutable($start),
            endDate: new \DateTimeImmutable('2026-11-02'),
            zone: 'A', academy: 'Bordeaux', schoolYear: '2026-2027',
        );
        $noel = new ImportedHoliday(
            label: 'Vacances de Noël',
            startDate: new \DateTimeImmutable('2026-12-19'),
            endDate: new \DateTimeImmutable('2027-01-04'),
            zone: 'A', academy: 'Bordeaux', schoolYear: '2026-2027',
        );
        $provider->queue = [
            [$toussaint('2026-10-17'), $noel],           // 1er import
            [$toussaint('2026-10-18'), $noel],           // 2e import : start_date Toussaint rectifié
        ];

        $importer = $this->importerWith($provider);

        $importer->import($family, '2026-2027');
        self::assertCount(2, $repo->findOfficialBySchoolYear($family, '2026-2027'), 'deux périodes importées');

        // Réimport avec un start_date rectifié : pas de 3e ligne orpheline, la Toussaint est unique.
        $importer->import($family, '2026-2027');
        $official = $repo->findOfficialBySchoolYear($family, '2026-2027');
        self::assertCount(2, $official, 'idempotence : périodes rectifiées balayées, pas de doublon');

        $toussaintDates = array_map(
            static fn ($h) => $h->getStartDate()->format('Y-m-d'),
            array_values(array_filter($official, static fn ($h) => 'Vacances de la Toussaint' === $h->getLabel())),
        );
        self::assertSame(['2026-10-18'], $toussaintDates, 'la Toussaint conserve le start_date rectifié');
    }
}
