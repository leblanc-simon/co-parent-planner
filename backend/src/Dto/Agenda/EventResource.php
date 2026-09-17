<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
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
 * fuseau famille. Le paramètre `overlay=` fusionne en lecture seule les événements des
 * familles liées activées (Lot 5).
 */
#[ApiResource(
    shortName: 'Event',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/events',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            // Paramètres de requête DÉCLARÉS : le contrat est la source du client Dart mobile, et
            // aucune opération n'exposait les siens — l'agenda n'était pas affichable depuis le seul
            // contrat (revue web C3, revue mobile C37 et C38/C45).
            parameters: [
                'from' => new QueryParameter(
                    description: 'Début de la fenêtre (date locale famille, AAAA-MM-JJ). Défaut : 1er du mois courant.',
                    // `type: string` + `pattern` plutôt que `format: date` : le générateur du
                    // client Dart mappe `format: date` sur `DateTime` et sérialise en ISO 8601
                    // complet, que `AgendaRange` refuse en 422. Le contrat doit décrire ce qui
                    // circule réellement.
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
                'to' => new QueryParameter(
                    description: 'Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant. Fenêtre maximale : '.AgendaRange::MAX_WINDOW_DAYS.' jours.',
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
                'category' => new QueryParameter(
                    description: 'Filtre de catégories, séparées par des virgules (ex. `school,health`). Absent = toutes.',
                    schema: ['type' => 'string'],
                ),
                'overlay' => new QueryParameter(
                    // CSV d'identifiants (EventsProvider), pas un booléen ni un uuid unique.
                    description: 'Identifiants des familles liées dont projeter les événements en overlay, séparés par des virgules (US-AG-4, lecture seule). Absent = aucun overlay.',
                    schema: ['type' => 'string'],
                ),
            ],
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
            // `scope` est la SEULE façon de distinguer « cette occurrence » de « toute la série » :
            // l'omettre du contrat rendait l'édition d'une série indevinable (revue C3).
            parameters: [
                'scope' => new QueryParameter(
                    description: "Portée de l'édition d'une série : `occurrence` (override de la seule occurrence `recurrenceDate`) ou `series` (cette occurrence et les suivantes, avec scission). Requis pour un événement récurrent, ignoré pour un ponctuel.",
                    schema: ['type' => 'string', 'enum' => ['occurrence', 'series']],
                ),
            ],
            read: false,
            processor: UpdateEventProcessor::class,
        ),
        new Delete(
            uriTemplate: '/events/{id}',
            output: false,
            parameters: [
                'scope' => new QueryParameter(
                    description: 'Portée de la suppression : `occurrence` (exclusion de la seule occurrence) ou `series`. Requis pour un événement récurrent.',
                    schema: ['type' => 'string', 'enum' => ['occurrence', 'series']],
                ),
                'recurrenceDate' => new QueryParameter(
                    description: "Occurrence visée par `scope=occurrence` : date d'origine (AAAA-MM-JJ) ou instant ISO 8601 renvoyé par l'API.",
                    schema: ['type' => 'string'],
                ),
                'from' => new QueryParameter(
                    description: 'Pivot de `scope=series` : supprime cette occurrence et les suivantes. Absent = toute la série.',
                    schema: ['type' => 'string'],
                ),
            ],
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
    /**
     * Version de la ligne à renvoyer dans `expectedVersion` d'un PATCH (verrou optimiste, §4.3).
     * `PATCH /events/{id}` l'acceptait déjà, mais aucune lecture ne l'exposait : la protection
     * contre l'écriture concurrente était donc hors de portée de tout client (revue 2026-08 C20).
     */
    public int $version = 1;
    /** @var array{freq: string, until?: string|null, count?: int|null, excludeHolidays?: bool}|null */
    public ?array $recurrence = null;

    // ---- Discriminant d'origine (Lot 5) ----------------------------------------------------------
    //
    // Avec `overlay=`, la collection mêle deux formes : les événements de la famille active et la
    // projection minimale des familles liées ({@see OverlayEventResource}). Le contrat ne déclarait
    // que la première, si bien qu'un client généré depuis l'OpenAPI ne pouvait pas distinguer les
    // deux — le mobile contournait par un appel HTTP écrit à la main. Ces quatre champs déclarent
    // l'union : ce sont exactement ceux que porte la projection liée.

    /** Origine de l'élément : `own` (famille active) ou `linked` (famille liée, lecture seule). */
    public string $source = 'own';
    /** Teinte imposée par l'origine (`#9C6FB0` pour une famille liée) ; `null` = palette de catégorie. */
    public ?string $color = null;
    /** La ressource est mutable via l'API — l'autorisation reste soumise au droit `agenda`. Toujours `false` en overlay. */
    public bool $editable = true;
    /** Famille liée d'origine, pour le regroupement UI ; `null` pour un événement de la famille active. */
    public ?string $linkedFamilyId = null;

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
        $dto->version = $o->version;

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
        $dto->version = $e->getVersion();

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
