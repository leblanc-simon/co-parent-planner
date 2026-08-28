<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\Membership;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyExceptionRepository;
use App\Repository\SchoolHolidayRepository;
use Psr\Clock\ClockInterface;

/**
 * Assemble le détail d'un jour de garde (Lot 3, US-CUST-6) : attributaire, origine de
 * l'attribution, et **transmission** « Retour chez {Parent} le {date} à {handoverTime} » calculée
 * à partir des jours matérialisés qui suivent — jamais d'une projection du rythme, pour que les
 * exceptions et les règles de vacances soient prises en compte comme partout ailleurs.
 */
final class DayDetailReader
{
    /**
     * Fenêtre de recherche du prochain changement d'attributaire. Le rythme le plus long du
     * catalogue s'étale sur 14 jours (`custom`), et une règle de vacances peut attribuer une
     * période entière à un seul parent : 45 jours couvrent les vacances d'été comme le reste
     * sans jamais balayer l'horizon complet (~18 mois).
     */
    private const int HANDOVER_LOOKAHEAD_DAYS = 45;

    public function __construct(
        private readonly CustodyDayRepository $days,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly SchoolHolidayRepository $holidays,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param \DateTimeImmutable $date date calendaire (Europe/Paris, à minuit) — même convention
     *                                 que le moteur (`CustodyDateMath`), jamais un instant
     */
    public function detail(Family $family, ?CustodyPattern $pattern, \DateTimeImmutable $date): DayDetail
    {
        $dayKey = $date->format('Y-m-d');

        $custodyDay = null;
        foreach ($this->days->findRange($family, $date, $date) as $candidate) {
            if ($candidate->getDate()->format('Y-m-d') === $dayKey) {
                $custodyDay = $candidate;
                break;
            }
        }
        $parent = $custodyDay?->getParentMembership();

        // Périodes couvrant ce jour. Elles sont affichées quelle que soit la source retenue par le
        // moteur : une exception prime sur une règle de vacances, mais savoir que le jour tombe
        // aussi dans une période de vacances explique le planning autour.
        $exceptions = $this->exceptions->findOverlapping($family, $date, $date);
        $holidays = $this->holidays->findOverlapping($family, $date, $date);

        [$handoverDate, $handoverParent] = $this->nextHandover($family, $date, $parent);

        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);

        return new DayDetail(
            date: $date,
            parent: $parent,
            source: $custodyDay?->getSource(),
            exception: $exceptions[0] ?? null,
            holiday: $holidays[0] ?? null,
            handoverDate: $handoverDate,
            handoverParent: $handoverParent,
            handoverTime: $pattern?->getHandoverTime(),
            isPast: $date->format('Y-m-d') < $today->format('Y-m-d'),
        );
    }

    /**
     * Premier jour matérialisé, après `$date`, attribué à un autre parent que `$parent`.
     *
     * Retourne `[null, null]` si le jour lui-même n'est pas attribué (rien à quitter), ou si aucun
     * changement n'apparaît dans la fenêtre — cas réel en fin d'horizon glissant, où annoncer une
     * transmission serait une invention.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?Membership}
     */
    private function nextHandover(Family $family, \DateTimeImmutable $date, ?Membership $parent): array
    {
        if (null === $parent) {
            return [null, null];
        }

        $currentId = (string) $parent->getId();
        $from = $date->modify('+1 day');
        $to = $date->modify('+'.self::HANDOVER_LOOKAHEAD_DAYS.' days');

        // `findRange` rend les jours triés par date croissante : le premier changement rencontré
        // est bien le prochain.
        foreach ($this->days->findRange($family, $from, $to) as $day) {
            $next = $day->getParentMembership();
            if (null !== $next && (string) $next->getId() !== $currentId) {
                return [$day->getDate(), $next];
            }
        }

        return [null, null];
    }
}
