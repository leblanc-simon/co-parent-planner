<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Entity\Exchange;
use Psr\Clock\ClockInterface;

/**
 * Détermine si un échange est « en retard » (Lot 6 §3.4). L'échéance est une **date** ; elle court
 * jusqu'à la **fin de journée** dans le fuseau de la famille. Marqueur **dérivé** (jamais matérialisé),
 * évalué au rendu via une horloge injectable (testabilité, transitions DST §4.1).
 */
final class OverdueChecker
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function isOverdue(Exchange $exchange, \DateTimeZone $tz): bool
    {
        $deadline = $exchange->getDeadline();
        if (null === $deadline || !$exchange->isOpen()) {
            return false;
        }

        // Fin de journée d'échéance dans le fuseau famille (gère le DST via la lib de fuseau).
        $endOfDeadline = new \DateTimeImmutable($deadline->format('Y-m-d').' 23:59:59.999999', $tz);

        return $this->clock->now() > $endOfDeadline;
    }
}
