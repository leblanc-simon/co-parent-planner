<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Event;
use App\Entity\Family;
use App\Enum\AuditAction;
use App\Exception\AgendaInputException;
use App\Exception\AgendaNotFoundException;
use App\Repository\EventRepository;
use App\Security\Agenda\AgendaAccess;
use App\Security\Auth\AuditLogger;
use App\Service\Agenda\AgendaPublisher;
use App\Service\Agenda\OccurrenceEditor;
use App\Service\Agenda\SeriesSplitter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-AG-5/6 — supprime un événement (agenda:edit). Pour une série, `?scope=occurrence` exclut une
 * occurrence (EXDATE, §3.4) et `?scope=series[&from=…]` supprime toute la série ou tout à partir d'un
 * pivot (§3.5) ; un événement ponctuel est supprimé directement.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteEventProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendaAccess $access,
        private readonly EventRepository $events,
        private readonly OccurrenceEditor $occurrenceEditor,
        private readonly SeriesSplitter $splitter,
        private readonly AgendaPublisher $publisher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->write();
        $event = $this->load($uriVariables['id'] ?? null, $ctx->family);
        $id = (string) $event->getId();

        $this->applyDelete($event, $ctx->family);

        $this->audit->log(
            AuditAction::EventDeleted,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Event',
            entityId: $id,
        ); // flush (commit)

        $this->publisher->publish($ctx->family);

        return null;
    }

    private function applyDelete(Event $event, Family $family): void
    {
        // Ponctuel : suppression directe.
        if (null === $event->getSeriesId()) {
            $this->em->remove($event);

            return;
        }

        // Override édité directement : on retire l'override (l'occurrence redevient virtuelle).
        if (!$event->isSeriesMaster()) {
            if ($event->isCancelled()) {
                throw new AgendaNotFoundException();
            }
            $this->em->remove($event);

            return;
        }

        // Maître : la portée est requise.
        match ($this->scope()) {
            'occurrence' => $this->occurrenceEditor->exclude($event, $this->query('recurrenceDate'), $family),
            'series' => $this->splitter->deleteSeries($event, $this->query('from'), $family),
            null => throw new AgendaInputException('agenda.error.scope_required'),
            default => throw new AgendaInputException('agenda.error.scope_invalid'),
        };
    }

    private function scope(): ?string
    {
        return $this->query('scope');
    }

    private function query(string $name): ?string
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get($name);

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
