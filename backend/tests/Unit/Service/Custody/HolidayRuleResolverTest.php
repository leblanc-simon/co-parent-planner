<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Custody;

use App\Enum\HolidayRuleKind;
use App\Service\Custody\HolidayRuleResolver;
use PHPUnit\Framework\TestCase;

/**
 * Moteur — règles de vacances (§3.3). Inclut le déterminisme de `eve_alternating` (année civile).
 */
final class HolidayRuleResolverTest extends TestCase
{
    private HolidayRuleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new HolidayRuleResolver();
    }

    private function d(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s);
    }

    public function testFixedParent(): void
    {
        $map = $this->resolver->assignments(HolidayRuleKind::FixedParent, ['parent' => 'B'], $this->d('2026-02-09'), $this->d('2026-02-13'));
        self::assertCount(5, $map);
        self::assertSame('B', $map['2026-02-09']);
        self::assertSame('B', $map['2026-02-13']);
    }

    public function testSplit5050(): void
    {
        // 4 jours : 2 firstHalf (A), 2 secondHalf (B).
        $map = $this->resolver->assignments(HolidayRuleKind::Split5050, ['firstHalf' => 'A', 'secondHalf' => 'B'], $this->d('2026-02-09'), $this->d('2026-02-12'));
        self::assertSame(['A', 'A', 'B', 'B'], array_values($map));
    }

    public function testAlternatingWeeks(): void
    {
        $map = $this->resolver->assignments(HolidayRuleKind::AlternatingWeeks, ['startParent' => 'A'], $this->d('2026-02-01'), $this->d('2026-02-14'));
        self::assertSame('A', $map['2026-02-01']); // semaine 0
        self::assertSame('A', $map['2026-02-07']);
        self::assertSame('B', $map['2026-02-08']); // semaine 1
    }

    public function testEveAlternatingAnchoredOnCivilYear(): void
    {
        // evenParent=A. 2026 pair → réveillons chez A ; 2027 impair → chez B.
        $params = ['evenParent' => 'A', 'baseRule' => ['kind' => 'split_50_50', 'params' => ['firstHalf' => 'A', 'secondHalf' => 'B']]];
        $map2026 = $this->resolver->assignments(HolidayRuleKind::EveAlternating, $params, $this->d('2026-12-23'), $this->d('2026-12-31'));
        self::assertSame('A', $map2026['2026-12-24'], 'réveillon 24/12 année paire → A');
        self::assertSame('A', $map2026['2026-12-31'], 'réveillon 31/12 année paire → A');

        $map2027 = $this->resolver->assignments(HolidayRuleKind::EveAlternating, $params, $this->d('2027-12-23'), $this->d('2027-12-31'));
        self::assertSame('B', $map2027['2027-12-24'], 'réveillon 24/12 année impaire → B');
    }

    public function testEveAlternatingDefaultsAreUsable(): void
    {
        // Sans params explicites : eves par défaut {12,24},{12,31}, baseRule split par défaut.
        $map = $this->resolver->assignments(HolidayRuleKind::EveAlternating, ['evenParent' => 'B'], $this->d('2026-12-23'), $this->d('2026-12-25'));
        self::assertSame('B', $map['2026-12-24'], 'année paire → evenParent (B)');
        self::assertArrayHasKey('2026-12-23', $map, 'jours non-réveillon couverts par baseRule');
    }

    public function testCustomAssignments(): void
    {
        $map = $this->resolver->assignments(
            HolidayRuleKind::Custom,
            ['assignments' => ['2026-02-10' => 'B', '2026-02-12' => 'A']],
            $this->d('2026-02-09'),
            $this->d('2026-02-13'),
        );
        self::assertSame('B', $map['2026-02-10']);
        self::assertSame('A', $map['2026-02-12']);
        self::assertArrayNotHasKey('2026-02-09', $map, 'jours non listés non assignés');
    }
}
