<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Custody;

use App\Entity\CustodyPattern;
use App\Enum\CustodyPatternKind;
use App\Service\Custody\BasePatternResolver;
use App\Service\Custody\CustodyDateMath;
use PHPUnit\Framework\TestCase;

/**
 * Moteur — base par modèle (§3.2). Motifs figés et conventions de jour de semaine (0=dimanche).
 */
final class BasePatternResolverTest extends TestCase
{
    private BasePatternResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new BasePatternResolver();
    }

    private function pattern(CustodyPatternKind $kind, string $anchor, int $handoverWeekday = 0): CustodyPattern
    {
        return (new CustodyPattern())
            ->setKind($kind)
            ->setAnchorDate(new \DateTimeImmutable($anchor))
            ->setHandoverWeekday($handoverWeekday);
    }

    private function d(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    public function testWeekdayConventionSundayIsZero(): void
    {
        self::assertSame(0, CustodyDateMath::weekday($this->d('2026-01-04')), 'dimanche = 0');
        self::assertSame(1, CustodyDateMath::weekday($this->d('2026-01-05')), 'lundi = 1');
        self::assertSame(6, CustodyDateMath::weekday($this->d('2026-01-03')), 'samedi = 6');
    }

    public function testAlternatingWeekByWeek(): void
    {
        // Ancre un dimanche, échange le dimanche : la semaine bascule chaque dimanche.
        $p = $this->pattern(CustodyPatternKind::Alternating, '2026-01-04', 0);
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-04')));
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-10'))); // même semaine
        self::assertSame('B', $this->resolver->slotFor($p, $this->d('2026-01-11'))); // dimanche suivant → bascule
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-18')));
    }

    public function testPattern2223CanonicalMotif(): void
    {
        $p = $this->pattern(CustodyPatternKind::Pattern2223, '2026-01-04');
        $expected = BasePatternResolver::MOTIF_223; // [A,A,B,B,A,A,A,B,B,A,A,B,B,B]
        foreach ($expected as $i => $slot) {
            $day = $this->d('2026-01-04')->modify("+{$i} days");
            self::assertSame($slot, $this->resolver->slotFor($p, $day), "jour n=$i");
        }
        // Le motif se répète après 14 jours.
        self::assertSame($expected[0], $this->resolver->slotFor($p, $this->d('2026-01-04')->modify('+14 days')));
    }

    public function testCustom14Slots(): void
    {
        $custom = ['A', 'B', 'A', 'B', 'A', 'B', 'A', 'B', 'A', 'B', 'A', 'B', 'A', 'B'];
        $p = $this->pattern(CustodyPatternKind::Custom, '2026-01-04')->setCustomPattern($custom);
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-04')));
        self::assertSame('B', $this->resolver->slotFor($p, $this->d('2026-01-05')));
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-18'))); // n=14 → %14=0
    }

    public function testMidweekWeekends(): void
    {
        $p = $this->pattern(CustodyPatternKind::MidweekWeekends, '2026-01-04', 0)->setSettings([
            'weekday_parents' => ['1' => 'A', '2' => 'A', '3' => 'B', '4' => 'B', '5' => 'A'],
            'weekend_days' => [0, 6],
            'weekend_start_parent' => 'A',
        ]);
        // Jours de semaine fixes.
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-05')), 'lundi → A');
        self::assertSame('B', $this->resolver->slotFor($p, $this->d('2026-01-07')), 'mercredi → B');
        // Week-end alterné : 1er week-end (semaine 0) → A, week-end suivant → B.
        self::assertSame('A', $this->resolver->slotFor($p, $this->d('2026-01-04')), 'dimanche semaine 0 → A');
        self::assertSame('B', $this->resolver->slotFor($p, $this->d('2026-01-11')), 'dimanche semaine 1 → B');
    }
}
