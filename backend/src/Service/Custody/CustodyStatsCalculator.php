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
     * `daysInWindow` = nombre de jours calendaires de [from, to] ; `total` = jours **matérialisés**
     * sur cette fenêtre. Les deux diffèrent quand le moteur n'a pas généré toute la fenêtre (rythme
     * ancré en cours de mois, horizon glissant) : les pourcentages portent alors sur une partie du
     * mois seulement, ce qu'un affichage « Statistiques du mois » doit dire (revue 2026-08 C19).
     *
     * @return array{parentACount:int, parentBCount:int, otherCount:int, total:int, daysInWindow:int, pctA:float, pctB:float}
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
            'daysInWindow' => 1 + (int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->days,
            'pctA' => $total > 0 ? round($a / $total * 100, 1) : 0.0,
            'pctB' => $total > 0 ? round($b / $total * 100, 1) : 0.0,
        ];
    }
}
