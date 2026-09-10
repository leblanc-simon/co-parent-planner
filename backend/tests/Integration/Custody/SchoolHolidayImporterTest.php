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
        $provider = $this->scriptedProvider();
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

    /**
     * Sans année explicite, l'import couvre **toutes** les années scolaires de l'horizon glissant :
     * lancé en été, il ne doit pas ramener seulement l'année scolaire qui s'achève (le symptôme
     * « l'import récupère les vacances de l'an dernier »).
     */
    public function testImportCoversEverySchoolYearOfTheHorizon(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $family = (new Family())->setName('Horizon')->setType(FamilyType::Separated)
            ->setSchoolZone('A')->setAcademy('Bordeaux');
        $em->persist($family);
        $em->flush();

        $provider = $this->scriptedProvider();
        $importer = $this->importerWith($provider);
        $importer->import($family); // sans année : l'horizon décide

        $years = $importer->schoolYearsInHorizon();
        self::assertSame($years, $provider->requestedYears, 'la source est interrogée pour chaque année de l\'horizon');
        self::assertContains($importer->currentSchoolYear(), $years, "l'année en cours est couverte");
        self::assertGreaterThanOrEqual(2, \count($years), '18 mois traversent au moins deux années scolaires');
        self::assertSame(array_values(array_unique($years)), $years, 'pas de doublon');

        // Continuité : chaque année commence là où la précédente finit.
        foreach ($years as $i => $year) {
            [$start, $end] = array_map('intval', explode('-', $year));
            self::assertSame($start + 1, $end, 'année scolaire bien formée');
            if ($i > 0) {
                [, $previousEnd] = array_map('intval', explode('-', $years[$i - 1]));
                self::assertSame($previousEnd, $start, 'années consécutives');
            }
        }
    }

    /**
     * Une année scolaire pas encore publiée renvoie une liste vide : l'import interroge désormais
     * l'année suivante à chaque passage, et une réponse vide ne doit **jamais** balayer un
     * calendrier déjà importé (ni les règles de vacances qui y pendent).
     */
    public function testEmptySourceResponseNeverSweepsExistingPeriods(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = self::getContainer()->get(SchoolHolidayRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(SchoolHolidayRepository::class, $repo);

        $family = (new Family())->setName('Vide')->setType(FamilyType::Separated)
            ->setSchoolZone('A')->setAcademy('Bordeaux');
        $em->persist($family);
        $em->flush();

        $provider = $this->scriptedProvider();
        $provider->queue = [
            [new ImportedHoliday(
                label: 'Vacances de Noël',
                startDate: new \DateTimeImmutable('2026-12-19'),
                endDate: new \DateTimeImmutable('2027-01-04'),
                zone: 'A', academy: 'Bordeaux', schoolYear: '2026-2027',
            )],
            [], // second passage : la source ne répond plus rien pour cette année
        ];
        $importer = $this->importerWith($provider);

        $importer->import($family, '2026-2027');
        self::assertCount(1, $repo->findOfficialBySchoolYear($family, '2026-2027'));

        self::assertSame(0, $importer->import($family, '2026-2027'), 'réponse vide : rien à importer');
        self::assertCount(1, $repo->findOfficialBySchoolYear($family, '2026-2027'), 'la période importée survit');
    }

    private function scriptedProvider(): ScriptedHolidaysProvider
    {
        return new ScriptedHolidaysProvider();
    }
}

/** Provider scripté : une file de résultats, un par appel de fetch(), et la trace des années demandées. */
final class ScriptedHolidaysProvider implements SchoolHolidaysProvider
{
    /** @var list<list<ImportedHoliday>> */
    public array $queue = [];

    /** @var list<string> */
    public array $requestedYears = [];

    public function fetch(string $zone, string $academy, string $schoolYear): array
    {
        $this->requestedYears[] = $schoolYear;

        return array_shift($this->queue) ?? [];
    }
}
