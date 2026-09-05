<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Exchange\CreateExchangeRequest;
use App\Dto\Exchange\ExchangeResource;
use App\Entity\Exchange;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Repository\ExchangeReadRepository;
use App\Repository\ExchangeRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Exchanges\ExchangeAccess;
use App\Security\FamilyContext;
use App\Service\Exchange\ExchangeAssembler;
use App\Service\Exchange\ExchangePublisher;
use App\Service\Exchange\ExchangeWriter;
use App\Service\Notification\NotificationEmitter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-EX-1 — crée une discussion ou une décision (exchanges:edit). Idempotent sur `Idempotency-Key`
 * (outbox mobile §4.2), sérialisé par `pg_advisory_xact_lock` sur (auteur, clé) : un rejeu renvoie
 * l'échange déjà créé. Fan-out temps réel **liste** après commit (badge). Émet `ExchangeOpened`.
 *
 * @implements ProcessorInterface<CreateExchangeRequest, ExchangeResource>
 */
final class CreateExchangeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeAccess $access,
        private readonly ExchangeWriter $writer,
        private readonly ExchangeRepository $exchanges,
        private readonly ExchangeReadRepository $reads,
        private readonly ExchangeAssembler $assembler,
        private readonly ExchangePublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExchangeResource
    {
        \assert($data instanceof CreateExchangeRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());

        $rawKey = $this->requestStack->getCurrentRequest()?->headers->get('Idempotency-Key');
        $key = \is_string($rawKey) && '' !== $rawKey ? $rawKey : null;

        $exchange = null !== $key ? $this->createIdempotent($ctx, $data, $key) : $this->createSimple($ctx, $data);

        // Ouvrir un échange, c'est l'avoir lu (X5) — sinon son auteur le retrouve en gras.
        $this->reads->markRead($ctx->membership, $exchange, $exchange->getCreatedAt());
        $this->em->flush();

        $this->publisher->publishList($ctx->family);
        // Notification e-mail (Lot 7) : nouvel échange → membres habilités (hors auteur).
        $this->notifier->emit(NotificationType::ExchangeOpened, $ctx->family, $ctx->membership, (string) $exchange->getId(), [
            'exchangeId' => (string) $exchange->getId(),
            'title' => $exchange->getTitle(),
        ]);

        return $this->assembler->detail($exchange, $tz, $ctx->membership);
    }

    private function createSimple(FamilyContext $ctx, CreateExchangeRequest $data): Exchange
    {
        $exchange = $this->writer->build($ctx->family, $ctx->membership, $data);
        $this->audit->log(
            AuditAction::ExchangeOpened,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Exchange',
            entityId: (string) $exchange->getId(),
        ); // flush (commit)

        return $exchange;
    }

    private function createIdempotent(FamilyContext $ctx, CreateExchangeRequest $data, string $key): Exchange
    {
        return $this->em->wrapInTransaction(function () use ($ctx, $data, $key): Exchange {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                // Verrou par (auteur, clé) — cf. C10 : la clé appartient à l'auteur, pas à la famille.
                ['k' => 'exchange_idem:'.$ctx->membership->getId().':'.$key],
            );

            $existing = $this->exchanges->findOneByIdempotencyKey($ctx->family, $ctx->membership, $key);
            if ($existing instanceof Exchange) {
                return $existing; // rejeu : aucun doublon
            }

            $exchange = $this->writer->build($ctx->family, $ctx->membership, $data);
            $exchange->setIdempotencyKey($key);
            $this->audit->persist(
                AuditAction::ExchangeOpened,
                $ctx->user(),
                $ctx->family,
                ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                entityType: 'Exchange',
                entityId: (string) $exchange->getId(),
            );

            return $exchange;
        });
    }
}
