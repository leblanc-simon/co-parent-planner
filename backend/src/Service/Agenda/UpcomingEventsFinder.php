<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Entity\Family;
use Psr\Clock\ClockInterface;

/**
 * « Les N prochains événements » pour l'Accueil (US-AG-7, Lot 4 §3.7). Borne = **instant courant**
 * (pas début de journée) : une occurrence dont l'heure est passée n'apparaît plus, mais un événement
 * en cours (fin ≥ maintenant) reste listé. Fenêtre initiale étendue par paliers (90 → 180 → 365 j)
 * jusqu'à atteindre la limite ou le plafond, pour ne pas masquer un événement lointain isolé.
 */
final class UpcomingEventsFinder
{
    /** @var list<int> paliers d'élargissement de la fenêtre (jours). */
    private const array LOOKAHEAD_STEPS = [90, 180, 365];

    public function __construct(
        private readonly AgendaReader $reader,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<Occurrence>
     */
    public function upcoming(Family $family, int $limit = 4): array
    {
        $now = $this->clock->now();
        $future = [];

        foreach (self::LOOKAHEAD_STEPS as $days) {
            $to = $now->modify(\sprintf('+%d days', $days));
            $occurrences = $this->reader->occurrences($family, $now, $to);
            $future = array_values(array_filter(
                $occurrences,
                static fn (Occurrence $o): bool => $o->startsAt >= $now
                    || (null !== $o->endsAt && $o->endsAt >= $now),
            ));

            if (\count($future) >= $limit) {
                break;
            }
        }

        return \array_slice($future, 0, $limit);
    }
}
