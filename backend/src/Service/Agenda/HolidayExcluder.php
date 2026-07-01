<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Entity\Family;
use App\Repository\SchoolHolidayRepository;

/**
 * Exclusion des occurrences tombant en vacances scolaires (option `excludeHolidays`, Lot 4 §3.8).
 * Les périodes proviennent des `SchoolHoliday` **déjà importés** (Lot 3) — jamais d'appel externe à
 * la volée. Si la famille n'a aucune période, l'option est sans effet. Ne s'applique **qu'aux
 * occurrences virtuelles** : un override explicite n'est jamais filtré (décision §3.8).
 */
final class HolidayExcluder
{
    public function __construct(
        private readonly SchoolHolidayRepository $holidays,
    ) {
    }

    /**
     * Périodes de vacances (dates locales inclusives Y-m-d) chevauchant la fenêtre.
     *
     * @return list<array{start: string, end: string}>
     */
    public function periods(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $out = [];
        foreach ($this->holidays->findOverlapping($family, $from, $to) as $holiday) {
            $out[] = [
                'start' => $holiday->getStartDate()->format('Y-m-d'),
                'end' => $holiday->getEndDate()->format('Y-m-d'),
            ];
        }

        return $out;
    }

    /**
     * L'occurrence (sa journée locale, ou son intervalle si multi-jours) chevauche-t-elle une période ?
     *
     * @param list<array{start: string, end: string}> $periods
     */
    public function intersects(OccurrenceInstant $instant, bool $allDay, \DateTimeZone $tz, array $periods): bool
    {
        $occStart = $instant->startsAt->setTimezone($tz)->format('Y-m-d');
        $occEnd = (!$allDay && null !== $instant->endsAt)
            ? $instant->endsAt->setTimezone($tz)->format('Y-m-d')
            : $occStart;

        foreach ($periods as $period) {
            if ($occStart <= $period['end'] && $occEnd >= $period['start']) {
                return true;
            }
        }

        return false;
    }
}
