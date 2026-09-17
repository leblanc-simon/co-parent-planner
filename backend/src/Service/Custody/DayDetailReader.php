<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\CustodyDaySource;
use App\Repository\CustodyDayRepository;
use App\Repository\CustodyExceptionRepository;
use App\Repository\MembershipRepository;
use App\Repository\SchoolHolidayRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Assemble le détail d'un jour de garde (Lot 3, US-CUST-6) : tranches horaires de la journée,
 * origine de l'attribution, et **transmission** « Retour chez {Parent} le {date} à {heure} » lue
 * sur les jours matérialisés qui suivent — jamais sur une projection du rythme, pour que les
 * exceptions et les règles de vacances soient prises en compte comme partout ailleurs.
 */
class DayDetailReader
{
    /**
     * Fenêtre de recherche de la prochaine transmission. Le rythme le plus long du catalogue
     * s'étale sur 14 jours (`custom`), et une règle de vacances peut attribuer une période entière
     * à un seul parent : 45 jours couvrent les vacances d'été comme le reste sans jamais balayer
     * l'horizon complet (~18 mois).
     */
    private const int HANDOVER_LOOKAHEAD_DAYS = 45;

    public function __construct(
        private readonly CustodyDayRepository $days,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly SchoolHolidayRepository $holidays,
        private readonly MembershipRepository $memberships,
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

        // La veille est lue avec le jour lui-même (une seule requête) : c'est elle qui décide si un
        // échange prolonge la garde en cours ou commence le matin même.
        $previousKey = $date->modify('-1 day')->format('Y-m-d');
        $custodyDay = null;
        $previousDay = null;
        foreach ($this->days->findRange($family, $date->modify('-1 day'), $date) as $candidate) {
            $candidateKey = $candidate->getDate()->format('Y-m-d');
            if ($candidateKey === $dayKey) {
                $custodyDay = $candidate;
            } elseif ($candidateKey === $previousKey) {
                $previousDay = $candidate;
            }
        }

        $parent = $custodyDay?->getParentMembership();
        $segments = null !== $custodyDay
            ? DaySegments::fromArray(
                $custodyDay->getSegments(),
                null !== $parent ? (string) $parent->getId() : null,
                $custodyDay->getSource(),
            )
            : null;

        // Périodes couvrant ce jour. Elles sont affichées quelle que soit la source retenue par le
        // moteur : une exception prime sur une règle de vacances, mais savoir que le jour tombe
        // aussi dans une période de vacances explique le planning autour.
        $exceptions = $this->exceptions->findOverlapping($family, $date, $date);
        $holidays = $this->holidays->findOverlapping($family, $date, $date);

        [$handoverDate, $handoverParentId, $handoverTime] = $this->nextHandover($family, $date, $segments);

        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $isPast = $date->format('Y-m-d') < $today->format('Y-m-d');

        [$swapDefaults, $swapTarget] = $this->swap($pattern, $date, $parent, $previousDay?->getParentMembership(), $isPast);

        return new DayDetail(
            date: $date,
            parent: $parent,
            segments: null !== $segments ? $this->resolve($segments, $pattern) : [],
            source: $custodyDay?->getSource(),
            exception: $exceptions[0] ?? null,
            holiday: $holidays[0] ?? null,
            handoverDate: $handoverDate,
            handoverParent: null !== $handoverParentId ? $this->membership($handoverParentId, $pattern) : null,
            handoverTime: $handoverTime,
            isPast: $isPast,
            swapDefaults: $swapDefaults,
            swapTarget: $swapTarget,
        );
    }

    /**
     * Tranches du jour, parents résolus en memberships.
     *
     * @return list<array{from: string, to: string, parent: ?Membership, source: CustodyDaySource}>
     */
    private function resolve(DaySegments $segments, ?CustodyPattern $pattern): array
    {
        return array_map(fn (DaySegment $s) => [
            'from' => $s->fromTime(),
            'to' => $s->toTime(),
            'parent' => null !== $s->parentId ? $this->membership($s->parentId, $pattern) : null,
            'source' => $s->source,
        ], $segments->segments);
    }

    /**
     * Membership d'un identifiant de tranche. Les deux parents du rythme couvrent la quasi-totalité
     * des cas sans requête ; une exception peut toutefois désigner un autre membre parent, qu'on va
     * alors chercher.
     */
    private function membership(string $id, ?CustodyPattern $pattern): ?Membership
    {
        foreach ([$pattern?->getParentA(), $pattern?->getParentB()] as $candidate) {
            if (null !== $candidate && (string) $candidate->getId() === $id) {
                return $candidate;
            }
        }

        return Uuid::isValid($id) ? $this->memberships->find(Uuid::fromString($id)) : null;
    }

    /**
     * Prochaine transmission : premier changement de parent **après** la fin de la journée
     * consultée, lu sur les tranches des jours suivants.
     *
     * Retourne `[null, null, null]` si la journée n'est pas matérialisée (rien à quitter) ou si
     * aucun changement n'apparaît dans la fenêtre — cas réel en fin d'horizon glissant, où annoncer
     * une transmission serait une invention.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?string, 2: ?string}
     */
    private function nextHandover(Family $family, \DateTimeImmutable $date, ?DaySegments $segments): array
    {
        if (null === $segments) {
            return [null, null, null];
        }

        $last = $segments->segments[\count($segments->segments) - 1];
        $currentId = $last->parentId;
        if (null === $currentId) {
            return [null, null, null];
        }

        $from = $date->modify('+1 day');
        $to = $date->modify('+'.self::HANDOVER_LOOKAHEAD_DAYS.' days');

        // `findRange` rend les jours triés par date croissante : le premier changement rencontré
        // est bien le prochain.
        foreach ($this->days->findRange($family, $from, $to) as $day) {
            $parentId = $day->getParentMembership() ? (string) $day->getParentMembership()->getId() : null;
            $dayS = DaySegments::fromArray($day->getSegments(), $parentId, $day->getSource());
            foreach ($dayS->segments as $segment) {
                if (null !== $segment->parentId && $segment->parentId !== $currentId) {
                    return [$day->getDate(), $segment->parentId, $segment->fromTime()];
                }
            }
        }

        return [null, null, null];
    }

    /**
     * Bornes proposées à « Échanger ce jour » et parent qui récupérerait la journée : l'autre
     * parent du rythme. Rien à proposer sur un jour passé (l'API refuse l'exception rétroactive) ni
     * sans rythme configuré.
     *
     * @return array{0: ?SwapDefaults, 1: ?Membership}
     */
    private function swap(?CustodyPattern $pattern, \DateTimeImmutable $date, ?Membership $parent, ?Membership $previousParent, bool $isPast): array
    {
        $a = $pattern?->getParentA();
        $b = $pattern?->getParentB();
        if (null === $pattern || null === $a || null === $b || null === $parent || $isPast) {
            return [null, null];
        }

        $target = (string) $a->getId() === (string) $parent->getId() ? $b : $a;

        return [
            SwapDefaults::forDay(
                $date,
                null !== $previousParent ? (string) $previousParent->getId() : null,
                (string) $target->getId(),
                $pattern->getHandoverTime(),
                $pattern->getDayStartTime(),
            ),
            $target,
        ];
    }
}
