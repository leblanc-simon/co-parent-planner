<?php

declare(strict_types=1);

namespace App\Tests\Integration\Custody;

use App\Entity\CustodyException;
use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\HolidayRule;
use App\Entity\Membership;
use App\Entity\SchoolHoliday;
use App\Entity\User;
use App\Enum\CustodyPatternKind;
use App\Enum\FamilyType;
use App\Enum\HolidayRuleKind;
use App\Enum\HolidaySource;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Service\Custody\CustodyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Pipeline du moteur (§3.1) : base → vacances → exceptions, priorité, idempotence, passé figé.
 */
final class CustodyGeneratorTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $em;
    private CustodyGenerator $generator;
    private \Doctrine\DBAL\Connection $db;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = self::getContainer();
        $em = $c->get(EntityManagerInterface::class);
        $generator = $c->get(CustodyGenerator::class);
        $db = $c->get(\Doctrine\DBAL\Connection::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(CustodyGenerator::class, $generator);
        self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $db);
        $this->em = $em;
        $this->generator = $generator;
        $this->db = $db;
    }

    /** @return array{0: Family, 1: Membership, 2: Membership} */
    private function seedFamily(): array
    {
        $family = (new Family())->setName('Garde')->setType(FamilyType::Separated);
        $this->em->persist($family);

        $parents = [];
        foreach (['a', 'b'] as $k) {
            $u = (new User())->setEmail("p{$k}-".uniqid().'@ex.fr');
            $u->setPasswordHash('x');
            $m = (new Membership())->setUser($u)->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
            $this->em->persist($u);
            $this->em->persist($m);
            $parents[] = $m;
        }
        $this->em->flush();

        return [$family, $parents[0], $parents[1]];
    }

    private function pattern(Family $f, Membership $a, Membership $b): CustodyPattern
    {
        $p = (new CustodyPattern())
            ->setFamily($f)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate(new \DateTimeImmutable('2026-06-21')) // dimanche
            ->setHandoverWeekday(0)
            ->setHandoverTime('19:00')
            ->setParentA($a)
            ->setParentB($b);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    private function parentOf(Family $f, string $date): ?string
    {
        // Lecture DBAL directe : le moteur écrit hors ORM, on évite le cache d'identité périmé.
        $pid = $this->db->fetchOne(
            'SELECT parent_membership_id FROM custody_days WHERE family_id = ? AND date = ?',
            [(string) $f->getId(), $date],
        );

        return false === $pid ? null : (string) $pid;
    }

    private function countDays(Family $f, string $from, string $to): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM custody_days WHERE family_id = ? AND date >= ? AND date <= ?',
            [(string) $f->getId(), $from, $to],
        );
    }

    public function testGeneratesBaseFreezesPastAndIsIdempotent(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$family, $a, $b] = $this->seedFamily();
        $this->pattern($family, $a, $b);

        $this->generator->generate($family);

        // Passé figé : aucun jour avant aujourd'hui.
        self::assertNull($this->parentOf($family, '2026-06-22'), 'jour passé non généré');
        // Aujourd'hui et futur générés (alternating ancré dimanche : semaine du 21/06 = A).
        self::assertSame((string) $a->getId(), $this->parentOf($family, '2026-06-23'));
        self::assertSame((string) $b->getId(), $this->parentOf($family, '2026-06-28'), 'dimanche suivant → B');

        $countAfterFirst = $this->countDays($family, '2026-06-23', '2028-01-01');
        // Idempotence : relancer ne crée pas de doublon.
        $this->generator->generate($family);
        $countAfterSecond = $this->countDays($family, '2026-06-23', '2028-01-01');
        self::assertSame($countAfterFirst, $countAfterSecond, 'génération idempotente');
        self::assertGreaterThan(500, $countAfterFirst, 'horizon ~18 mois matérialisé');
    }

    public function testPriorityExceptionOverHolidayOverBase(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$family, $a, $b] = $this->seedFamily();
        $this->pattern($family, $a, $b);
        $this->generator->generate($family);

        // Une période de vacances "tout chez B" du 1 au 10 juillet.
        $holiday = (new SchoolHoliday())->setFamily($family)->setLabel('Été')
            ->setStartDate(new \DateTimeImmutable('2026-07-01'))->setEndDate(new \DateTimeImmutable('2026-07-10'))
            ->setSource(HolidaySource::Manual);
        $rule = (new HolidayRule())->setKind(HolidayRuleKind::FixedParent)->setParams(['parent' => 'B']);
        $holiday->setRule($rule);
        $this->em->persist($holiday);
        $this->em->persist($rule);
        $this->em->flush();

        $this->generator->generate($family);
        self::assertSame((string) $b->getId(), $this->parentOf($family, '2026-07-05'), 'vacances écrasent la base');

        // Une exception "chez A" le 5 juillet (priorité max).
        $exc = (new CustodyException())->setFamily($family)
            ->setStartDate(new \DateTimeImmutable('2026-07-05'))->setEndDate(new \DateTimeImmutable('2026-07-05'))
            ->setParentMembership($a);
        $this->em->persist($exc);
        $this->em->flush();
        $this->generator->applyException($exc);
        self::assertSame((string) $a->getId(), $this->parentOf($family, '2026-07-05'), 'exception écrase vacances');

        // Régénérer ne doit PAS écraser l'exception.
        $this->generator->generate($family);
        self::assertSame((string) $a->getId(), $this->parentOf($family, '2026-07-05'), 'exception préservée après régénération');

        // Supprimer l'exception → le jour redevient vacances (B).
        $this->em->remove($exc);
        $this->em->flush();
        $this->generator->recomputeWindow($family, new \DateTimeImmutable('2026-07-05'), new \DateTimeImmutable('2026-07-05'));
        self::assertSame((string) $b->getId(), $this->parentOf($family, '2026-07-05'), 'retour aux vacances après suppression de l\'exception');
    }
}
