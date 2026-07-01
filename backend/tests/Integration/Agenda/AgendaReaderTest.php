<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agenda;

use App\Entity\Event;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\SchoolHoliday;
use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\FamilyType;
use App\Enum\HolidaySource;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Repository\EventRepository;
use App\Repository\SchoolHolidayRepository;
use App\Service\Agenda\AgendaReader;
use App\Service\Agenda\HolidayExcluder;
use App\Service\Agenda\Occurrence;
use App\Service\Agenda\OccurrenceExpander;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestration BD de l'agenda (Lot 4 §3.3/§3.6/§3.8) : expansion + overrides + EXDATE + exclusion
 * vacances + filtre catégorie + tri. Le calcul de dates pur est couvert par OccurrenceExpanderTest.
 */
final class AgendaReaderTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $em;
    private AgendaReader $reader;
    private \DateTimeZone $paris;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;

        // AgendaReader n'est pas encore consommé par un service public (providers = §6) : on le
        // construit depuis ses dépendances plutôt que via le conteneur (qui l'inline/supprime).
        $eventRepo = $em->getRepository(Event::class);
        $holidayRepo = $em->getRepository(SchoolHoliday::class);
        self::assertInstanceOf(EventRepository::class, $eventRepo);
        self::assertInstanceOf(SchoolHolidayRepository::class, $holidayRepo);
        $this->reader = new AgendaReader($eventRepo, new OccurrenceExpander(), new HolidayExcluder($holidayRepo));
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    public function testExpandsSeriesAppliesOverrideAndExdateAndSortsWithPointEvent(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', $this->paris));
        [$family, $parent] = $this->seedFamily();

        // Série hebdomadaire mardi 17:30, 4 occurrences (23/06, 30/06, 07/07, 14/07).
        $seriesId = Uuid::v7();
        $master = $this->event($family, $parent, 'Tennis', EventCategory::Sport, '2026-06-23 17:30');
        $master->setSeriesId($seriesId)->setRecurrence(['freq' => 'weekly', 'until' => null, 'count' => 4, 'excludeHolidays' => false]);

        // Override du 30/06 déplacé à 19:00.
        $override = $this->event($family, $parent, 'Tennis (reporté)', EventCategory::Sport, '2026-06-30 19:00');
        $override->setSeriesId($seriesId)->setIsOverride(true)->setRecurrenceDate(new \DateTimeImmutable('2026-06-30'));

        // EXDATE du 07/07 (occurrence supprimée).
        $exdate = (new Event())->setFamily($family)->setTitle('x')->setCategory(EventCategory::Sport)
            ->setStartsAt($this->parisToUtc('2026-07-07 17:30'))
            ->setSeriesId($seriesId)->setCancelled(true)->setRecurrenceDate(new \DateTimeImmutable('2026-07-07'));
        $this->em->persist($exdate);

        // Événement ponctuel (musique) le 25/06.
        $this->event($family, $parent, 'Piano', EventCategory::Musique, '2026-06-25 10:00');
        $this->em->flush();

        $occ = $this->reader->occurrences($family, $this->utc('2026-06-01 00:00'), $this->utc('2026-08-01 00:00'));

        self::assertSame(
            ['2026-06-23 17:30', '2026-06-25 10:00', '2026-06-30 19:00', '2026-07-14 17:30'],
            $this->local($occ),
            'expansion + override (30/06 → 19:00) + EXDATE (07/07 absent) + ponctuel, triés par date',
        );
        // Le 30/06 est bien l'override matérialisé.
        self::assertTrue($occ[2]->isOverride);
        self::assertSame('Tennis (reporté)', $occ[2]->title);
    }

    public function testExcludesHolidaysButKeepsExplicitOverride(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', $this->paris));
        [$family, $parent] = $this->seedFamily();

        $seriesId = Uuid::v7();
        $master = $this->event($family, $parent, 'Tennis', EventCategory::Sport, '2026-06-23 17:30');
        $master->setSeriesId($seriesId)->setRecurrence(['freq' => 'weekly', 'until' => null, 'count' => 4, 'excludeHolidays' => true]);

        // Vacances couvrant le 07/07 (occurrence virtuelle → exclue).
        $holiday = (new SchoolHoliday())->setFamily($family)->setLabel('Été')
            ->setStartDate(new \DateTimeImmutable('2026-07-06'))->setEndDate(new \DateTimeImmutable('2026-07-12'))
            ->setSource(HolidaySource::Manual);
        $this->em->persist($holiday);

        // Mais un override explicite le 14/07, placé dans une AUTRE période de vacances → conservé (§3.8).
        $holiday2 = (new SchoolHoliday())->setFamily($family)->setLabel('Été 2')
            ->setStartDate(new \DateTimeImmutable('2026-07-13'))->setEndDate(new \DateTimeImmutable('2026-07-20'))
            ->setSource(HolidaySource::Manual);
        $this->em->persist($holiday2);
        $override = $this->event($family, $parent, 'Tennis (maintenu)', EventCategory::Sport, '2026-07-14 18:00');
        $override->setSeriesId($seriesId)->setIsOverride(true)->setRecurrenceDate(new \DateTimeImmutable('2026-07-14'));
        $this->em->flush();

        $occ = $this->reader->occurrences($family, $this->utc('2026-06-01 00:00'), $this->utc('2026-08-01 00:00'));

        self::assertSame(
            ['2026-06-23 17:30', '2026-06-30 17:30', '2026-07-14 18:00'],
            $this->local($occ),
            '07/07 exclu (vacances) mais override du 14/07 conservé malgré les vacances',
        );
    }

    public function testCategoryFilterIsUnion(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', $this->paris));
        [$family, $parent] = $this->seedFamily();
        $this->event($family, $parent, 'Tennis', EventCategory::Sport, '2026-06-23 17:30');
        $this->event($family, $parent, 'Piano', EventCategory::Musique, '2026-06-24 10:00');
        $this->event($family, $parent, 'Dentiste', EventCategory::Sante, '2026-06-25 09:00');
        $this->em->flush();

        $occ = $this->reader->occurrences(
            $family,
            $this->utc('2026-06-01 00:00'),
            $this->utc('2026-07-01 00:00'),
            [EventCategory::Sport, EventCategory::Musique],
        );

        self::assertSame(['Tennis', 'Piano'], array_map(static fn (Occurrence $o): string => $o->title, $occ));
    }

    /** @return array{0: Family, 1: Membership} */
    private function seedFamily(): array
    {
        $family = (new Family())->setName('Agenda')->setType(FamilyType::Separated);
        $this->em->persist($family);
        $user = (new User())->setEmail('p-'.uniqid().'@ex.fr')->setPasswordHash('x');
        $parent = (new Membership())->setUser($user)->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $this->em->persist($user);
        $this->em->persist($parent);
        $this->em->flush();

        return [$family, $parent];
    }

    private function event(Family $family, Membership $responsible, string $title, EventCategory $category, string $startParis): Event
    {
        $event = (new Event())
            ->setFamily($family)
            ->setTitle($title)
            ->setCategory($category)
            ->setResponsibleMembership($responsible)
            ->setStartsAt($this->parisToUtc($startParis));
        $this->em->persist($event);

        return $event;
    }

    /**
     * @param list<Occurrence> $occ
     *
     * @return list<string>
     */
    private function local(array $occ): array
    {
        return array_map(
            fn (Occurrence $o): string => $o->startsAt->setTimezone($this->paris)->format('Y-m-d H:i'),
            $occ,
        );
    }

    private function parisToUtc(string $s): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($s, $this->paris))->setTimezone(new \DateTimeZone('UTC'));
    }

    private function utc(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }
}
