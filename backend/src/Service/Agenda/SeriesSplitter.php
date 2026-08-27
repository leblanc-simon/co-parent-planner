<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Dto\Agenda\UpdateEventRequest;
use App\Entity\Event;
use App\Entity\Family;
use App\Exception\AgendaInputException;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Édition/suppression **de toute la série** (US-AG-6, Lot 4 §3.5), sémantique « cette occurrence et
 * les suivantes » (THISANDFUTURE) : sans pivot (ou pivot = première occurrence), on modifie le maître
 * en place ; sinon on **scinde** — le maître est clos la veille du pivot et une nouvelle série reprend
 * à partir du pivot, les overrides/EXDATE ≥ pivot lui étant réaffectés. Idempotent (§3.5).
 */
final class SeriesSplitter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OccurrenceExpander $expander,
        private readonly EventRepository $events,
    ) {
    }

    /** PATCH scope=series. Renvoie l'événement maître de la série contenant l'occurrence pivot. */
    public function editSeries(Event $master, UpdateEventRequest $data, Family $family, EventWriter $writer): Event
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $firstKey = $master->getStartsAt()->setTimezone($tz)->format('Y-m-d');
        $pivot = null !== $data->recurrenceDate ? $this->parseDate($data->recurrenceDate, $tz) : null;

        // Pivot absent ou = première occurrence → modification en place, pas de scission.
        if (null === $pivot || $pivot->format('Y-m-d') <= $firstKey) {
            $writer->applyFields($master, $data, $family);
            if (null !== $data->recurrence) {
                $this->applyRecurrence($master, $data->recurrence, $tz, $writer);
            }

            return $master;
        }

        $originalRule = RecurrenceRule::fromArray($master->getRecurrence());
        $pivotInstant = $this->expander->occurrenceOn($originalRule, $master->getStartsAt(), $master->getEndsAt(), $tz, $pivot);
        if (null === $pivotInstant) {
            throw new AgendaInputException('agenda.error.occurrence_invalid');
        }

        $seriesId = $this->seriesId($master);
        $newSeriesId = Uuid::v7();

        // Nouvelle série à partir du pivot.
        $tail = $this->copyFrom($master)
            ->setStartsAt($pivotInstant->startsAt)
            ->setEndsAt($pivotInstant->endsAt)
            ->setSeriesId($newSeriesId);
        $writer->applyFields($tail, $data, $family);
        $tailRule = null !== $data->recurrence
            ? $writer->normalizeRecurrence($data->recurrence, $tail->getStartsAt(), $tz)
            : $this->deriveTailRule($originalRule, $master, $pivot, $tz)->toArray();

        // freq=none fourni au pivot → le tail dégénère en événement **ponctuel** (pas une série).
        $dissolved = null === $tailRule;
        if ($dissolved) {
            $tail->setSeriesId(null)->setRecurrence(null);
        } else {
            $tail->setRecurrence($tailRule);
        }
        $this->em->persist($tail);

        // Fermer le maître la veille du pivot (until, count effacé).
        $this->closeAt($master, $originalRule, $pivot);

        // Overrides/EXDATE ≥ pivot : réaffectés à la nouvelle série, ou supprimés si la série est
        // dissoute. Les overrides reçoivent en plus les champs édités : « toute la série (à partir de
        // cette date) » laissait intacte toute occurrence déjà personnalisée — y compris celle que
        // l'utilisateur avait sous les yeux en lançant l'édition, qui gardait donc son ancien titre
        // (revue 2026-08 C5). Seuls les champs descriptifs sont propagés : l'heure propre d'une
        // occurrence déplacée est une modification voulue, la réécrire déplacerait tout au même
        // instant. Les EXDATE (occurrences annulées) restent annulées.
        $pivotKey = $pivot->format('Y-m-d');
        foreach ($this->events->findBySeriesId($seriesId) as $row) {
            if ($row === $master) {
                continue;
            }
            $rowKey = $row->getRecurrenceDate()?->format('Y-m-d');
            if (null === $rowKey || $rowKey < $pivotKey) {
                continue;
            }
            if ($dissolved) {
                $this->em->remove($row);

                continue;
            }
            $row->setSeriesId($newSeriesId);
            if ($row->isOverride()) {
                $writer->applyDescriptiveFields($row, $data, $family);
            }
        }

        return $tail;
    }

    /** DELETE scope=series. Sans pivot : supprime toute la série ; avec pivot : this-and-future. */
    public function deleteSeries(Event $master, ?string $fromRaw, Family $family): void
    {
        $seriesId = $this->seriesId($master);

        if (null === $fromRaw || '' === $fromRaw) {
            foreach ($this->events->findBySeriesId($seriesId) as $row) {
                $this->em->remove($row);
            }

            return;
        }

        $tz = new \DateTimeZone($family->getTimezone());
        $pivot = $this->parseDate($fromRaw, $tz);
        $pivotKey = $pivot->format('Y-m-d');
        $this->closeAt($master, RecurrenceRule::fromArray($master->getRecurrence()), $pivot);

        foreach ($this->events->findBySeriesId($seriesId) as $row) {
            if ($row === $master) {
                continue;
            }
            $rowKey = $row->getRecurrenceDate()?->format('Y-m-d');
            if (null !== $rowKey && $rowKey >= $pivotKey) {
                $this->em->remove($row);
            }
        }
    }

    /** Clôt le maître la veille du pivot : `until` = pivot − 1 j, `count` effacé. */
    private function closeAt(Event $master, RecurrenceRule $rule, \DateTimeImmutable $pivot): void
    {
        $dayBefore = $pivot->modify('-1 day');
        $master->setRecurrence((new RecurrenceRule($rule->freq, $dayBefore, null, $rule->excludeHolidays))->toArray());
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function applyRecurrence(Event $master, array $raw, \DateTimeZone $tz, EventWriter $writer): void
    {
        $normalized = $writer->normalizeRecurrence($raw, $master->getStartsAt(), $tz);
        if (null === $normalized) {
            // La série devient un événement ponctuel : dissoudre overrides/EXDATE.
            $seriesId = $master->getSeriesId();
            if (null !== $seriesId) {
                foreach ($this->events->findBySeriesId($seriesId) as $row) {
                    if ($row !== $master) {
                        $this->em->remove($row);
                    }
                }
            }
            $master->setRecurrence(null)->setSeriesId(null);

            return;
        }
        $master->setRecurrence($normalized);
    }

    private function deriveTailRule(RecurrenceRule $original, Event $master, \DateTimeImmutable $pivot, \DateTimeZone $tz): RecurrenceRule
    {
        if (null !== $original->count) {
            $before = $this->countBeforePivot($original, $master, $pivot, $tz);

            return new RecurrenceRule($original->freq, null, max(1, $original->count - $before), $original->excludeHolidays);
        }

        return new RecurrenceRule($original->freq, $original->until, null, $original->excludeHolidays);
    }

    private function countBeforePivot(RecurrenceRule $original, Event $master, \DateTimeImmutable $pivot, \DateTimeZone $tz): int
    {
        $utc = new \DateTimeZone('UTC');
        $dayBeforeEnd = (new \DateTimeImmutable($pivot->modify('-1 day')->format('Y-m-d').' 23:59:59', $tz))->setTimezone($utc);

        return \count($this->expander->expand($original, $master->getStartsAt(), $master->getEndsAt(), $tz, $master->getStartsAt(), $dayBeforeEnd));
    }

    /** Pivot de scission : `Y-m-d` **ou** instant ISO renvoyé par l'API (revue C9). */
    private function parseDate(string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        return OccurrenceDate::parse($raw, $tz);
    }

    private function seriesId(Event $master): Uuid
    {
        $seriesId = $master->getSeriesId();
        \assert(null !== $seriesId);

        return $seriesId;
    }

    private function copyFrom(Event $master): Event
    {
        $event = (new Event())
            ->setFamily($master->getFamily())
            ->setTitle($master->getTitle())
            ->setCategory($master->getCategory())
            ->setResponsibleMembership($master->getResponsibleMembership())
            ->setLocation($master->getLocation())
            ->setDescription($master->getDescription())
            ->setAllDay($master->isAllDay())
            ->setWholeFamily($master->isWholeFamily())
            ->setCreatedByMembership($master->getCreatedByMembership());
        foreach ($master->getChildren() as $child) {
            $event->addChild($child);
        }

        return $event;
    }
}
