<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Dto\Agenda\UpdateEventRequest;
use App\Entity\Event;
use App\Entity\Family;
use App\Exception\AgendaConflictException;
use App\Exception\AgendaInputException;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Édition/suppression d'une **occurrence unique** d'une série (US-AG-5, Lot 4 §3.4). Matérialise un
 * override (occurrence déplacée/modifiée) ou un EXDATE (occurrence exclue), rattachés au `seriesId`.
 * L'occurrence ciblée doit exister (§4.11) ; l'exclusion prime sur un override (§4.3).
 */
final class OccurrenceEditor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OccurrenceExpander $expander,
        private readonly EventRepository $events,
    ) {
    }

    /** PATCH scope=occurrence — upsert d'un override. Renvoie l'override (persisté). */
    public function override(Event $master, UpdateEventRequest $data, Family $family, EventWriter $writer): Event
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $recurrenceDate = $this->parseRecurrenceDate($data->recurrenceDate, $tz);
        $instant = $this->occurrenceOrFail($master, $recurrenceDate, $tz);
        $seriesId = $this->seriesId($master);
        $key = $recurrenceDate->format('Y-m-d');

        $override = null;
        foreach ($this->events->findBySeriesId($seriesId) as $row) {
            if ($key !== $row->getRecurrenceDate()?->format('Y-m-d')) {
                continue;
            }
            if ($row->isCancelled()) {
                throw new AgendaConflictException('agenda.error.occurrence_cancelled');
            }
            if ($row->isOverride()) {
                $override = $row;
            }
        }

        if (null === $override) {
            $override = $this->copyFrom($master)
                ->setStartsAt($instant->startsAt)
                ->setEndsAt($instant->endsAt)
                ->setSeriesId($seriesId)
                ->setIsOverride(true)
                ->setRecurrenceDate($recurrenceDate);
            $this->em->persist($override);
        }

        $writer->applyFields($override, $data, $family);

        return $override;
    }

    /** DELETE scope=occurrence — pose un EXDATE (idempotent) et neutralise un éventuel override. */
    public function exclude(Event $master, ?string $recurrenceDateRaw, Family $family): void
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $recurrenceDate = $this->parseRecurrenceDate($recurrenceDateRaw, $tz);
        $this->occurrenceOrFail($master, $recurrenceDate, $tz);
        $seriesId = $this->seriesId($master);
        $key = $recurrenceDate->format('Y-m-d');

        foreach ($this->events->findBySeriesId($seriesId) as $row) {
            if ($key !== $row->getRecurrenceDate()?->format('Y-m-d')) {
                continue;
            }
            if ($row->isCancelled()) {
                return; // déjà exclue : no-op idempotent (§4.3)
            }
            if ($row->isOverride()) {
                $this->em->remove($row); // l'exclusion neutralise l'override
            }
        }

        $exdate = $this->copyFrom($master)
            ->setStartsAt($master->getStartsAt())
            ->setSeriesId($seriesId)
            ->setRecurrenceDate($recurrenceDate)
            ->setCancelled(true);
        $this->em->persist($exdate);
    }

    private function occurrenceOrFail(Event $master, \DateTimeImmutable $recurrenceDate, \DateTimeZone $tz): OccurrenceInstant
    {
        $instant = $this->expander->occurrenceOn(
            RecurrenceRule::fromArray($master->getRecurrence()),
            $master->getStartsAt(),
            $master->getEndsAt(),
            $tz,
            $recurrenceDate,
        );
        if (null === $instant) {
            throw new AgendaInputException('agenda.error.occurrence_invalid');
        }

        return $instant;
    }

    private function parseRecurrenceDate(?string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            throw new AgendaInputException('agenda.error.occurrence_invalid');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw new AgendaInputException('agenda.error.occurrence_invalid');
        }

        return $date;
    }

    private function seriesId(Event $master): \Symfony\Component\Uid\Uuid
    {
        $seriesId = $master->getSeriesId();
        \assert(null !== $seriesId);

        return $seriesId;
    }

    /** Nouvel événement reprenant les champs descriptifs du maître (hors dates/série). */
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
