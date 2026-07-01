<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Entity\Child;
use App\Entity\Event;
use App\Entity\Membership;
use App\Enum\EventCategory;
use Symfony\Component\Uid\Uuid;

/**
 * Occurrence concrète d'agenda, prête pour le rendu (Lot 4 §3.6). Produite par l'`AgendaReader`
 * à partir d'un événement ponctuel, d'une occurrence virtuelle d'une série, ou d'un override.
 * Porte des références d'entités (responsable, enfants) — c'est un VO de présentation, pas un modèle
 * persisté (les occurrences virtuelles ne sont jamais stockées, §3.1).
 */
final class Occurrence
{
    /**
     * @param list<Child> $children
     */
    public function __construct(
        public readonly Uuid $eventId,
        public readonly ?Uuid $seriesId,
        public readonly \DateTimeImmutable $recurrenceDate,
        public readonly \DateTimeImmutable $startsAt,
        public readonly ?\DateTimeImmutable $endsAt,
        public readonly bool $allDay,
        public readonly EventCategory $category,
        public readonly string $title,
        public readonly ?string $location,
        public readonly ?string $description,
        public readonly ?Membership $responsibleMembership,
        public readonly bool $wholeFamily,
        public readonly array $children,
        public readonly bool $isRecurring,
        public readonly bool $isOverride,
    ) {
    }

    /** Occurrence issue directement d'un événement matérialisé (ponctuel ou override). */
    public static function fromEvent(Event $event, \DateTimeImmutable $recurrenceDate, bool $isRecurring, bool $isOverride): self
    {
        return new self(
            $event->getId(),
            $event->getSeriesId(),
            $recurrenceDate,
            $event->getStartsAt(),
            $event->getEndsAt(),
            $event->isAllDay(),
            $event->getCategory(),
            $event->getTitle(),
            $event->getLocation(),
            $event->getDescription(),
            $event->getResponsibleMembership(),
            $event->isWholeFamily(),
            self::childrenOf($event),
            $isRecurring,
            $isOverride,
        );
    }

    /** Occurrence **virtuelle** d'une série : champs du maître, instant issu de l'expansion. */
    public static function virtual(Event $master, OccurrenceInstant $instant): self
    {
        return new self(
            $master->getId(),
            $master->getSeriesId(),
            $instant->recurrenceDate,
            $instant->startsAt,
            $instant->endsAt,
            $master->isAllDay(),
            $master->getCategory(),
            $master->getTitle(),
            $master->getLocation(),
            $master->getDescription(),
            $master->getResponsibleMembership(),
            $master->isWholeFamily(),
            self::childrenOf($master),
            true,
            false,
        );
    }

    public function dateKey(): string
    {
        return $this->recurrenceDate->format('Y-m-d');
    }

    /**
     * @return list<Child>
     */
    private static function childrenOf(Event $event): array
    {
        return array_values($event->getChildren()->toArray());
    }
}
