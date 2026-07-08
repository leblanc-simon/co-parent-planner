<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agenda\EventResource;
use App\Dto\Agenda\UpdateEventRequest;
use App\Entity\Event;
use App\Entity\Family;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Exception\AgendaConflictException;
use App\Exception\AgendaInputException;
use App\Exception\AgendaNotFoundException;
use App\Repository\EventRepository;
use App\Security\Agenda\AgendaAccess;
use App\Security\Auth\AuditLogger;
use App\Service\Agenda\AgendaPublisher;
use App\Service\Agenda\EventWriter;
use App\Service\Agenda\OccurrenceEditor;
use App\Service\Agenda\SeriesSplitter;
use App\Service\Notification\NotificationEmitter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-AG-5/6 — édite un événement (agenda:edit). Pour une série, `?scope=occurrence|series` distingue
 * l'override d'une occurrence (§3.4) de l'édition « this-and-future » avec scission (§3.5) ; un
 * événement ponctuel est modifié directement (scope ignoré). Verrou optimiste via `expectedVersion`.
 *
 * @implements ProcessorInterface<UpdateEventRequest, EventResource>
 */
final class UpdateEventProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendaAccess $access,
        private readonly EventRepository $events,
        private readonly EventWriter $writer,
        private readonly OccurrenceEditor $occurrenceEditor,
        private readonly SeriesSplitter $splitter,
        private readonly AgendaPublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventResource
    {
        \assert($data instanceof UpdateEventRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $event = $this->load($uriVariables['id'] ?? null, $ctx->family);

        // Verrou optimiste : version périmée côté client → 409 (contrôle en mémoire par Doctrine).
        if (null !== $data->expectedVersion) {
            try {
                $this->em->lock($event, LockMode::OPTIMISTIC, $data->expectedVersion);
            } catch (OptimisticLockException) {
                throw new AgendaConflictException();
            }
        }

        try {
            $result = $this->apply($event, $data, $ctx->family);
            $this->audit->log(
                AuditAction::EventUpdated,
                $ctx->user(),
                $ctx->family,
                ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                entityType: 'Event',
                entityId: (string) $result->getId(),
            ); // flush (commit)
        } catch (OptimisticLockException) {
            // Course concurrente (TOCTOU) : une autre écriture a bougé la version au flush → 409.
            throw new AgendaConflictException();
        }

        $this->publisher->publish($ctx->family);
        // Notification e-mail (Lot 7) : événement modifié → clé par version (une notif par édition).
        $this->notifier->emit(NotificationType::AgendaEventUpdated, $ctx->family, $ctx->membership, $result->getId().':'.$result->getVersion(), [
            'title' => $result->getTitle(),
        ]);

        return EventResource::fromEvent($result, $tz);
    }

    private function apply(Event $event, UpdateEventRequest $data, Family $family): Event
    {
        // Ponctuel : édition directe (scope sans objet).
        if (null === $event->getSeriesId()) {
            $this->writer->applyFields($event, $data, $family);

            return $event;
        }

        // Ligne de série non-maître : override édité directement, EXDATE non éditable.
        if (!$event->isSeriesMaster()) {
            if ($event->isCancelled()) {
                throw new AgendaNotFoundException();
            }
            $this->writer->applyFields($event, $data, $family);

            return $event;
        }

        // Maître : la portée est requise.
        return match ($this->scope()) {
            'occurrence' => $this->occurrenceEditor->override($event, $data, $family, $this->writer),
            'series' => $this->splitter->editSeries($event, $data, $family, $this->writer),
            null => throw new AgendaInputException('agenda.error.scope_required'),
            default => throw new AgendaInputException('agenda.error.scope_invalid'),
        };
    }

    private function scope(): ?string
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get('scope');

        return \is_string($raw) && '' !== $raw ? $raw : null;
    }

    private function load(mixed $id, Family $family): Event
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new AgendaNotFoundException();
        }
        $event = $this->events->find(Uuid::fromString($id));
        if (!$event instanceof Event || !$event->getFamily()->getId()->equals($family->getId())) {
            throw new AgendaNotFoundException();
        }

        return $event;
    }
}
