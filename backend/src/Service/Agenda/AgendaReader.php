<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Entity\Event;
use App\Entity\Family;
use App\Enum\EventCategory;
use App\Repository\EventRepository;

/**
 * Assemble les occurrences d'agenda d'une famille sur une fenêtre [from, to] (Lot 4 §3.6) :
 * événements ponctuels + expansion des séries, avec application des overrides/EXDATE (§3.3) et de
 * l'exclusion vacances (§3.8), filtrage catégorie, et tri (jour local, all-day d'abord, heure).
 * Ne matérialise aucune occurrence virtuelle (§3.1).
 */
final class AgendaReader
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly OccurrenceExpander $expander,
        private readonly HolidayExcluder $holidays,
    ) {
    }

    /**
     * @param list<EventCategory>|null $categories filtre (union) ; null = toutes catégories
     *
     * @return list<Occurrence>
     */
    public function occurrences(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to, ?array $categories = null): array
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $result = [];

        // 1) Événements ponctuels.
        foreach ($this->events->findPointEventsOverlapping($family, $from, $to) as $point) {
            $result[] = Occurrence::fromEvent($point, $this->localMidnight($point->getStartsAt(), $tz), false, false);
        }

        // 2) Regrouper les lignes de série : maîtres, overrides (par date), EXDATE (par date).
        /** @var array<string, Event> $masters */
        $masters = [];
        /** @var array<string, array<string, Event>> $overridesByDate */
        $overridesByDate = [];
        /** @var array<string, array<string, true>> $exdates */
        $exdates = [];
        /** @var list<Event> $overrideRows */
        $overrideRows = [];

        foreach ($this->events->findSeriesRowsWithin($family, $to) as $row) {
            $seriesId = (string) $row->getSeriesId();
            $dateKey = $row->getRecurrenceDate()?->format('Y-m-d');
            if ($row->isCancelled()) {
                if (null !== $dateKey) {
                    $exdates[$seriesId][$dateKey] = true;
                }

                continue;
            }
            if ($row->isOverride()) {
                if (null !== $dateKey) {
                    $overridesByDate[$seriesId][$dateKey] = $row;
                }
                $overrideRows[] = $row;

                continue;
            }
            $masters[$seriesId] = $row;
        }

        // 3) Expanser chaque maître, appliquer EXDATE / overrides / exclusion vacances.
        /** @var list<array{start: string, end: string}>|null $holidayPeriods */
        $holidayPeriods = null;
        foreach ($masters as $seriesId => $master) {
            $rule = RecurrenceRule::fromArray($master->getRecurrence());
            if ($rule->excludeHolidays && null === $holidayPeriods) {
                $holidayPeriods = $this->holidays->periods($family, $from, $to);
            }

            foreach ($this->expander->expand($rule, $master->getStartsAt(), $master->getEndsAt(), $tz, $from, $to) as $instant) {
                $key = $instant->dateKey();
                if (isset($exdates[$seriesId][$key]) || isset($overridesByDate[$seriesId][$key])) {
                    continue; // occurrence supprimée, ou remplacée par un override (émis en phase 4)
                }
                if ($rule->excludeHolidays && $this->holidays->intersects($instant, $master->isAllDay(), $tz, $holidayPeriods ?? [])) {
                    continue;
                }
                $result[] = Occurrence::virtual($master, $instant);
            }
        }

        // 4) Overrides dont la date effective tombe dans la fenêtre (y compris déplacés, §4.11).
        //    Un override devenu **orphelin** (sa date d'origine n'est plus une occurrence de la règle
        //    courante du maître, ex. après un changement de freq) est ignoré au rendu (§4.11).
        foreach ($overrideRows as $override) {
            if (!$this->overlapsWindow($override, $from, $to)) {
                continue;
            }
            $recurrenceDate = $override->getRecurrenceDate();
            if (null === $recurrenceDate) {
                continue;
            }
            $master = $masters[(string) $override->getSeriesId()] ?? null;
            if (null !== $master && !$this->isValidOccurrence($master, $recurrenceDate, $tz)) {
                continue; // orphelin : la date n'est plus produite par la règle du maître
            }
            $result[] = Occurrence::fromEvent($override, $recurrenceDate, true, true);
        }

        // 5) Filtre catégories (union).
        if (null !== $categories && [] !== $categories) {
            $result = array_values(array_filter(
                $result,
                static fn (Occurrence $o): bool => \in_array($o->category, $categories, true),
            ));
        }

        // 6) Tri : jour local, all-day d'abord, heure, titre, id (§3.6).
        usort($result, static function (Occurrence $a, Occurrence $b) use ($tz): int {
            return [
                $a->startsAt->setTimezone($tz)->format('Y-m-d'),
                $a->allDay ? 0 : 1,
                $a->startsAt->getTimestamp(),
                $a->title,
                (string) $a->eventId,
            ] <=> [
                $b->startsAt->setTimezone($tz)->format('Y-m-d'),
                $b->allDay ? 0 : 1,
                $b->startsAt->getTimestamp(),
                $b->title,
                (string) $b->eventId,
            ];
        });

        return $result;
    }

    private function isValidOccurrence(Event $master, \DateTimeImmutable $recurrenceDate, \DateTimeZone $tz): bool
    {
        return null !== $this->expander->occurrenceOn(
            RecurrenceRule::fromArray($master->getRecurrence()),
            $master->getStartsAt(),
            $master->getEndsAt(),
            $tz,
            $recurrenceDate,
        );
    }

    private function overlapsWindow(Event $event, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        $end = $event->getEndsAt() ?? $event->getStartsAt();

        return $event->getStartsAt() <= $to && $end >= $from;
    }

    private function localMidnight(\DateTimeImmutable $instant, \DateTimeZone $tz): \DateTimeImmutable
    {
        return $instant->setTimezone($tz)->setTime(0, 0);
    }
}
