<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Dto\Agenda\CreateEventRequest;
use App\Dto\Agenda\UpdateEventRequest;
use App\Entity\Child;
use App\Entity\Event;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\EventCategory;
use App\Enum\MembershipRole;
use App\Enum\RecurrenceFreq;
use App\Exception\AgendaInputException;
use App\Repository\ChildRepository;
use App\Repository\MembershipRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Valide et construit un `Event` à la création (US-AG-1/2, Lot 4 §4.6) : titre non vide après trim,
 * cible enfant(s) XOR « toute la famille », responsable = parent/beau-parent actif de la famille,
 * dates ISO 8601 **avec offset** (sinon 422), normalisation `all_day`, récurrence (`until` XOR
 * `count`, bornes). Toute violation → 422.
 */
final class EventWriter
{
    /** ISO 8601 / RFC 3339 avec offset obligatoire (Z ou ±HH:MM) — §4.6. */
    private const string ISO_8601_WITH_OFFSET = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/';

    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly ChildRepository $children,
    ) {
    }

    public function build(Family $family, Membership $creator, CreateEventRequest $data): Event
    {
        $tz = new \DateTimeZone($family->getTimezone());
        $utc = new \DateTimeZone('UTC');

        $title = trim((string) $data->title);
        if ('' === $title) {
            throw new AgendaInputException('agenda.error.title_required');
        }

        $category = EventCategory::tryFrom((string) $data->category);
        if (null === $category) {
            throw new AgendaInputException('agenda.error.category_invalid');
        }

        $responsible = $this->resolveResponsible($family, (string) $data->responsibleMembershipId);
        $children = $this->resolveTarget($family, $data->wholeFamily, $data->childIds);

        [$startsAt, $endsAt] = $this->resolveInterval($data->startsAt, $data->endsAt, $data->allDay, $tz);
        $recurrence = $this->normalizeRecurrence($data->recurrence, $startsAt, $tz);

        $event = (new Event())
            ->setFamily($family)
            ->setTitle($title)
            ->setCategory($category)
            ->setResponsibleMembership($responsible)
            ->setLocation($this->trimToNull($data->location))
            ->setDescription($this->trimToNull($data->description))
            ->setStartsAt($startsAt->setTimezone($utc))
            ->setEndsAt($endsAt?->setTimezone($utc))
            ->setAllDay($data->allDay)
            ->setWholeFamily($data->wholeFamily)
            ->setCreatedByMembership($creator);

        foreach ($children as $child) {
            $event->addChild($child);
        }

        if (null !== $recurrence) {
            $event->setRecurrence($recurrence)->setSeriesId(Uuid::v7());
        }

        return $event;
    }

    /**
     * Applique les champs **fournis** (non null) d'une requête d'édition sur un événement existant
     * (US-AG-5/6). Ne touche ni la récurrence ni la série (gérées par la scission). Mêmes validations
     * que la création (titre, catégorie, cible, responsable, intervalle, all-day).
     */
    public function applyFields(Event $event, UpdateEventRequest $data, Family $family): void
    {
        $utc = new \DateTimeZone('UTC');
        $tz = new \DateTimeZone($family->getTimezone());

        $this->applyDescriptiveFields($event, $data, $family);

        $allDay = $data->allDay ?? $event->isAllDay();
        if ($allDay) {
            $dayStart = null !== $data->startsAt
                ? $this->parseLocalDay($data->startsAt, $tz)
                : $event->getStartsAt()->setTimezone($tz)->setTime(0, 0);
            $start = $dayStart;
            $end = $dayStart->setTime(23, 59, 59);
        } else {
            $start = null !== $data->startsAt ? $this->parseInstant($data->startsAt) : $event->getStartsAt();
            if (null !== $data->endsAt) {
                $end = '' === $data->endsAt ? null : $this->parseInstant($data->endsAt);
            } else {
                $end = $event->getEndsAt();
            }
            if (null !== $end && $end < $start) {
                throw new AgendaInputException('agenda.error.interval_invalid');
            }
        }
        $event->setAllDay($allDay)->setStartsAt($start->setTimezone($utc))->setEndsAt($end?->setTimezone($utc));
    }

    /**
     * Champs **descriptifs** fournis (titre, catégorie, responsable, lieu, description, cible) —
     * sans jamais toucher aux dates ni au marqueur journée entière.
     *
     * Extrait de {@see self::applyFields} pour la scission de série : « toute la série à partir de
     * cette date » doit propager le nouveau titre aux occurrences déjà personnalisées, mais surtout
     * pas leur horaire propre, qui est une modification voulue (revue 2026-08 C5).
     */
    public function applyDescriptiveFields(Event $event, UpdateEventRequest $data, Family $family): void
    {
        if (null !== $data->title) {
            $title = trim($data->title);
            if ('' === $title) {
                throw new AgendaInputException('agenda.error.title_required');
            }
            $event->setTitle($title);
        }
        if (null !== $data->category) {
            $category = EventCategory::tryFrom($data->category);
            if (null === $category) {
                throw new AgendaInputException('agenda.error.category_invalid');
            }
            $event->setCategory($category);
        }
        if (null !== $data->responsibleMembershipId) {
            $event->setResponsibleMembership($this->resolveResponsible($family, $data->responsibleMembershipId));
        }
        if (null !== $data->location) {
            $event->setLocation($this->trimToNull($data->location));
        }
        if (null !== $data->description) {
            $event->setDescription($this->trimToNull($data->description));
        }
        if (null !== $data->wholeFamily || null !== $data->childIds) {
            $wholeFamily = $data->wholeFamily ?? $event->isWholeFamily();
            $children = $this->resolveTarget($family, $wholeFamily, $data->childIds ?? []);
            $event->setWholeFamily($wholeFamily);
            foreach ($event->getChildren()->toArray() as $existing) {
                $event->removeChild($existing);
            }
            foreach ($children as $child) {
                $event->addChild($child);
            }
        }
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable|null} start/end (fuseau famille)
     */
    private function resolveInterval(?string $startsAt, ?string $endsAt, bool $allDay, \DateTimeZone $tz): array
    {
        if ($allDay) {
            $dayStart = $this->parseLocalDay($startsAt, $tz);

            return [$dayStart, $dayStart->setTime(23, 59, 59)];
        }

        $start = $this->parseInstant($startsAt);
        $end = (null !== $endsAt && '' !== $endsAt) ? $this->parseInstant($endsAt) : null;
        if (null !== $end && $end < $start) {
            throw new AgendaInputException('agenda.error.interval_invalid');
        }

        return [$start, $end];
    }

    private function resolveResponsible(Family $family, string $id): Membership
    {
        if (!Uuid::isValid($id)) {
            throw new AgendaInputException('agenda.error.responsible_invalid');
        }
        $membership = $this->memberships->find(Uuid::fromString($id));
        if (!$membership instanceof Membership
            || !$membership->getFamily()->getId()->equals($family->getId())
            || !$membership->isActive()
            || MembershipRole::Child === $membership->getRole()) {
            throw new AgendaInputException('agenda.error.responsible_invalid');
        }

        return $membership;
    }

    /**
     * @param list<string> $childIds
     *
     * @return list<Child>
     */
    private function resolveTarget(Family $family, bool $wholeFamily, array $childIds): array
    {
        $childIds = array_values(array_unique(array_map(static fn (string $id): string => trim($id), $childIds)));

        if ($wholeFamily && [] !== $childIds) {
            throw new AgendaInputException('agenda.error.target_ambiguous');
        }
        if (!$wholeFamily && [] === $childIds) {
            throw new AgendaInputException('agenda.error.target_required');
        }

        $children = [];
        foreach ($childIds as $id) {
            if (!Uuid::isValid($id)) {
                throw new AgendaInputException('agenda.error.child_invalid');
            }
            $child = $this->children->find(Uuid::fromString($id));
            if (!$child instanceof Child || !$child->getFamily()->getId()->equals($family->getId())) {
                throw new AgendaInputException('agenda.error.child_invalid');
            }
            $children[] = $child;
        }

        return $children;
    }

    /**
     * Valide et normalise le champ `recurrence` d'une série (réutilisé par la scission §3.5).
     *
     * @param array<string, mixed>|null $raw
     *
     * @return array{freq: string, until: string|null, count: int|null, excludeHolidays: bool}|null
     */
    public function normalizeRecurrence(?array $raw, \DateTimeImmutable $startsAt, \DateTimeZone $tz): ?array
    {
        if (null === $raw) {
            return null;
        }

        $freq = RecurrenceFreq::tryFrom(\is_string($raw['freq'] ?? null) ? $raw['freq'] : 'none');
        if (null === $freq) {
            throw new AgendaInputException('agenda.error.recurrence_freq_invalid');
        }
        if (!$freq->isRecurring()) {
            return null;
        }

        $hasUntil = isset($raw['until']) && '' !== $raw['until'];
        $hasCount = isset($raw['count']);
        if ($hasUntil && $hasCount) {
            throw new AgendaInputException('agenda.error.recurrence_bounds_exclusive');
        }
        if (!$hasUntil && !$hasCount) {
            throw new AgendaInputException('agenda.error.recurrence_unbounded');
        }

        $count = null;
        if ($hasCount) {
            if (!\is_int($raw['count'])) {
                throw new AgendaInputException('agenda.error.recurrence_count_invalid');
            }
            $count = $raw['count'];
            if ($count <= 0 || $count > OccurrenceExpander::MAX_OCCURRENCES_PER_SERIES) {
                throw new AgendaInputException('agenda.error.recurrence_count_invalid');
            }
        }

        $until = null;
        if ($hasUntil) {
            $until = $this->parseDate((string) $raw['until'], $tz);
            $anchorDate = $startsAt->setTimezone($tz)->format('Y-m-d');
            if ($until->format('Y-m-d') < $anchorDate) {
                throw new AgendaInputException('agenda.error.recurrence_until_before_start');
            }
            if ($this->exceedsMax($freq, $startsAt->setTimezone($tz), $until)) {
                throw new AgendaInputException('agenda.error.recurrence_too_long');
            }
        }

        return (new RecurrenceRule($freq, $until, $count, (bool) ($raw['excludeHolidays'] ?? false)))->toArray();
    }

    private function exceedsMax(RecurrenceFreq $freq, \DateTimeImmutable $anchorLocal, \DateTimeImmutable $until): bool
    {
        $anchorMidnight = $anchorLocal->setTime(0, 0);
        $days = (int) $anchorMidnight->diff($until->setTime(0, 0))->days;
        $occurrences = match ($freq) {
            RecurrenceFreq::Weekly => intdiv($days, 7) + 1,
            RecurrenceFreq::Biweekly => intdiv($days, 14) + 1,
            RecurrenceFreq::Monthly => $this->monthsBetween($anchorMidnight, $until) + 1,
            RecurrenceFreq::None => 1,
        };

        return $occurrences > OccurrenceExpander::MAX_OCCURRENCES_PER_SERIES;
    }

    private function monthsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $diff = $from->diff($to);

        return $diff->y * 12 + $diff->m;
    }

    /** Instant ISO 8601 **avec offset** obligatoire (§4.6) → 422 sinon. */
    private function parseInstant(?string $raw): \DateTimeImmutable
    {
        $raw = (string) $raw;
        if (1 !== preg_match(self::ISO_8601_WITH_OFFSET, $raw)) {
            throw new AgendaInputException('agenda.error.date_invalid');
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new AgendaInputException('agenda.error.date_invalid');
        }
    }

    /** Journée locale (minuit fuseau famille) pour un événement all-day : accepte `Y-m-d` ou un instant ISO. */
    private function parseLocalDay(?string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        $raw = (string) $raw;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if (false !== $date && $date->format('Y-m-d') === $raw) {
            return $date;
        }

        $instant = $this->parseInstant($raw);
        $localDate = $instant->setTimezone($tz)->format('Y-m-d');

        return new \DateTimeImmutable($localDate.' 00:00:00', $tz);
    }

    private function parseDate(string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if (false === $date || $date->format('Y-m-d') !== $raw) {
            throw new AgendaInputException('agenda.error.date_invalid');
        }

        return $date;
    }

    private function trimToNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
