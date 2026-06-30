<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\Family;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyPatternRepository;

/**
 * Statistiques de garde (Lot 3 §3.6) : jours par parent sur une fenêtre, calculés **à la volée**
 * sur les `CustodyDay` **effectifs** (reflétant exceptions + vacances). Jamais stockées.
 */
final class CustodyStatsCalculator
{
    public function __construct(
        private readonly CustodyDayRepository $days,
        private readonly CustodyPatternRepository $patterns,
    ) {
    }

    /**
     * @return array{parentACount:int, parentBCount:int, otherCount:int, total:int, pctA:float, pctB:float}
     */
    public function compute(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $pattern = $this->patterns->findOneByFamily($family);
        $aId = (string) ($pattern?->getParentA()?->getId() ?? '');
        $bId = (string) ($pattern?->getParentB()?->getId() ?? '');

        $a = $b = $other = 0;
        foreach ($this->days->findRange($family, $from, $to) as $day) {
            $pid = (string) ($day->getParentMembership()?->getId() ?? '');
            if ('' !== $aId && $pid === $aId) {
                ++$a;
            } elseif ('' !== $bId && $pid === $bId) {
                ++$b;
            } else {
                ++$other;
            }
        }
        $total = $a + $b + $other;

        return [
            'parentACount' => $a,
            'parentBCount' => $b,
            'otherCount' => $other,
            'total' => $total,
            'pctA' => $total > 0 ? round($a / $total * 100, 1) : 0.0,
            'pctB' => $total > 0 ? round($b / $total * 100, 1) : 0.0,
        ];
    }
}
