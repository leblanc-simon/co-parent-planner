<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Dto\Family\FamilyResource;
use App\Entity\Child;
use App\Entity\Event;
use App\Service\Agenda\Occurrence;
use App\State\Agenda\AgendaRange;
use App\State\Agenda\CreateEventProcessor;
use App\State\Agenda\DeleteEventProcessor;
use App\State\Agenda\EventsProvider;
use App\State\Agenda\UpdateEventProcessor;

/**
 * Événement / occurrence d'agenda exposé (Lot 4 §6.1). `GET /families/{id}/events` renvoie les
 * **occurrences** sur [from, to] (une par occurrence de série) ; `POST` crée un événement ou une
 * série et renvoie l'événement maître. `startsAt`/`endsAt` sont formatés en ISO 8601 avec offset du
 * fuseau famille. Le paramètre `overlay=` est **réservé** (Lot 5, ignoré ici).
 */
#[ApiResource(
    shortName: 'Event',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/events',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            // `from`/`to`/`overlay` étaient lus par AgendaRange et EventsProvider sans être
            // déclarés au contrat : le client mobile généré depuis l'OpenAPI ne pouvait donc
            // pas les passer, et restait bloqué sur la fenêtre par défaut (le mois courant).
            // Revue mobile 2026-08, constats C37 et C38/C45.
            openapi: new Operation(
                parameters: [
                    new Parameter(
                        name: 'from',
                        in: 'query',
                        description: 'Début de la fenêtre, date locale `Y-m-d`. Défaut : premier jour du mois courant.',
                        required: false,
                        // `type: string` + `pattern` plutôt que `format: date` : le
                        // générateur du client Dart mappe `format: date` sur `DateTime` et
                        // sérialise en ISO 8601 complet, que `AgendaRange` refuse en 422.
                        // Le contrat doit décrire ce qui circule réellement.
                        schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    ),
                    new Parameter(
                        name: 'to',
                        in: 'query',
                        description: 'Fin de la fenêtre (incluse), date locale `Y-m-d`. Défaut : dernier jour du mois courant. Fenêtre maximale : '.AgendaRange::MAX_WINDOW_DAYS.' jours.',
                        required: false,
                        schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    ),
                    new Parameter(
                        name: 'overlay',
                        in: 'query',
                        description: 'Fusionne les événements des familles liées activées (Lot 5), en lecture seule.',
                        required: false,
                        schema: ['type' => 'boolean'],
                    ),
                ],
            ),
            provider: EventsProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/events',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateEventRequest::class,
            read: false,
            processor: CreateEventProcessor::class,
        ),
        new Patch(
            uriTemplate: '/events/{id}',
            input: UpdateEventRequest::class,
            read: false,
            processor: UpdateEventProcessor::class,
        ),
        new Delete(
            uriTemplate: '/events/{id}',
            output: false,
            read: false,
            processor: DeleteEventProcessor::class,
        ),
    ],
)]
class EventResource
{
    public string $id = '';
    public ?string $seriesId = null;
    public string $title = '';
    public string $category = '';
    public ?string $responsibleMembershipId = null;
    public bool $wholeFamily = false;
    /** @var list<string> */
    public array $childIds = [];
    public ?string $location = null;
    public ?string $description = null;
    public string $startsAt = '';
    public ?string $endsAt = null;
    public bool $allDay = false;
    public ?string $recurrenceDate = null;
    public bool $isRecurring = false;
    public bool $isOverride = false;
    /** @var array{freq: string, until?: string|null, count?: int|null, excludeHolidays?: bool}|null */
    public ?array $recurrence = null;

    public static function fromOccurrence(Occurrence $o, \DateTimeZone $tz): self
    {
        $dto = new self();
        $dto->id = (string) $o->eventId;
        $dto->seriesId = null !== $o->seriesId ? (string) $o->seriesId : null;
        $dto->title = $o->title;
        $dto->category = $o->category->value;
        $dto->responsibleMembershipId = null !== $o->responsibleMembership ? (string) $o->responsibleMembership->getId() : null;
        $dto->wholeFamily = $o->wholeFamily;
        $dto->childIds = self::childIds($o->children);
        $dto->location = $o->location;
        $dto->description = $o->description;
        $dto->startsAt = $o->startsAt->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->endsAt = $o->endsAt?->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->allDay = $o->allDay;
        $dto->recurrenceDate = $o->recurrenceDate->format('Y-m-d');
        $dto->isRecurring = $o->isRecurring;
        $dto->isOverride = $o->isOverride;

        return $dto;
    }

    public static function fromEvent(Event $e, \DateTimeZone $tz): self
    {
        $dto = new self();
        $dto->id = (string) $e->getId();
        $dto->seriesId = null !== $e->getSeriesId() ? (string) $e->getSeriesId() : null;
        $dto->title = $e->getTitle();
        $dto->category = $e->getCategory()->value;
        $dto->responsibleMembershipId = null !== $e->getResponsibleMembership() ? (string) $e->getResponsibleMembership()->getId() : null;
        $dto->wholeFamily = $e->isWholeFamily();
        $dto->childIds = self::childIds(array_values($e->getChildren()->toArray()));
        $dto->location = $e->getLocation();
        $dto->description = $e->getDescription();
        $dto->startsAt = $e->getStartsAt()->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->endsAt = $e->getEndsAt()?->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->allDay = $e->isAllDay();
        $dto->recurrenceDate = $e->getRecurrenceDate()?->format('Y-m-d');
        $dto->isRecurring = $e->isSeriesMaster() || $e->isOverride();
        $dto->isOverride = $e->isOverride();
        $dto->recurrence = $e->getRecurrence();

        return $dto;
    }

    /**
     * @param list<Child> $children
     *
     * @return list<string>
     */
    private static function childIds(array $children): array
    {
        $ids = array_map(static fn (Child $c): string => (string) $c->getId(), $children);
        sort($ids);

        return $ids;
    }
}
