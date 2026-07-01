<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agenda\CreateEventRequest;
use App\Dto\Agenda\EventResource;
use App\Entity\Event;
use App\Enum\AuditAction;
use App\Repository\EventRepository;
use App\Security\Agenda\AgendaAccess;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyContext;
use App\Service\Agenda\AgendaPublisher;
use App\Service\Agenda\EventWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-AG-1/2 — crée un événement ponctuel ou une série (agenda:edit). Publie l'événement temps réel
 * après commit. Idempotent sur l'en-tête `Idempotency-Key` (outbox mobile, §4.3) : la clé est
 * persistée sur l'événement (unicité `(famille, clé)`) et les créations concurrentes portant la même
 * clé sont **sérialisées par un verrou advisory transactionnel** — un rejeu renvoie l'événement déjà
 * créé (pas de doublon, pas d'erreur d'unicité).
 *
 * @implements ProcessorInterface<CreateEventRequest, EventResource>
 */
final class CreateEventProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgendaAccess $access,
        private readonly EventWriter $writer,
        private readonly EventRepository $events,
        private readonly AgendaPublisher $publisher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventResource
    {
        \assert($data instanceof CreateEventRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());

        $rawKey = $this->requestStack->getCurrentRequest()?->headers->get('Idempotency-Key');
        $key = \is_string($rawKey) && '' !== $rawKey ? $rawKey : null;

        $event = null !== $key ? $this->createIdempotent($ctx, $data, $key) : $this->createSimple($ctx, $data);

        // Après commit : diffusion temps réel best-effort (n'invalide jamais l'écriture).
        $this->publisher->publish($ctx->family);

        return EventResource::fromEvent($event, $tz);
    }

    private function createSimple(FamilyContext $ctx, CreateEventRequest $data): Event
    {
        $event = $this->writer->build($ctx->family, $ctx->membership, $data);
        $this->em->persist($event);
        $this->audit->log(
            AuditAction::EventCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Event',
            entityId: (string) $event->getId(),
        ); // flush (commit)

        return $event;
    }

    /**
     * Création idempotente : sérialisée par `pg_advisory_xact_lock` sur (famille, clé). Les requêtes
     * concurrentes de même clé attendent le commit de la première, puis retrouvent l'événement créé
     * et le renvoient — au lieu de heurter l'index unique et de renvoyer une 500 (§4.3).
     */
    private function createIdempotent(FamilyContext $ctx, CreateEventRequest $data, string $key): Event
    {
        return $this->em->wrapInTransaction(function () use ($ctx, $data, $key): Event {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                ['k' => 'agenda_idem:'.$ctx->family->getId().':'.$key],
            );

            $existing = $this->events->findOneByIdempotencyKey($ctx->family, $key);
            if ($existing instanceof Event) {
                return $existing; // rejeu : aucun doublon
            }

            $event = $this->writer->build($ctx->family, $ctx->membership, $data);
            $event->setIdempotencyKey($key);
            $this->em->persist($event);
            // persist (sans flush) : committé atomiquement avec l'événement par wrapInTransaction.
            $this->audit->persist(
                AuditAction::EventCreated,
                $ctx->user(),
                $ctx->family,
                ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                entityType: 'Event',
                entityId: (string) $event->getId(),
            );

            return $event;
        });
    }
}
