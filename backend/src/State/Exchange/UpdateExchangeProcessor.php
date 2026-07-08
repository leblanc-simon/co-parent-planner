<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Exchange\ExchangeResource;
use App\Dto\Exchange\UpdateExchangeRequest;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Exception\ExchangeConflictException;
use App\Security\Auth\AuditLogger;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangeAssembler;
use App\Service\Exchange\ExchangePublisher;
use App\Service\Exchange\ExchangeWriter;
use App\Service\Notification\NotificationEmitter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-EX-6 — clôture / édite un échange (exchanges:edit) : clôture d'une discussion (`status=decided`),
 * forçage d'une décision (`decidedOptionId`), édition d'échéance ; réouverture interdite (§4.5).
 * Verrou optimiste via `expectedVersion`. Fan-out détail (+ liste si passage à `decided`).
 *
 * @implements ProcessorInterface<UpdateExchangeRequest, ExchangeResource>
 */
final class UpdateExchangeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeAccess $access,
        private readonly ExchangeWriter $writer,
        private readonly ExchangeAssembler $assembler,
        private readonly ExchangePublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExchangeResource
    {
        \assert($data instanceof UpdateExchangeRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $exchange = $this->access->loadInFamily($uriVariables['id'] ?? null, $ctx->family);

        if (null !== $data->expectedVersion) {
            try {
                $this->em->lock($exchange, LockMode::OPTIMISTIC, $data->expectedVersion);
            } catch (OptimisticLockException) {
                throw new ExchangeConflictException();
            }
        }

        try {
            $becameDecided = $this->writer->applyPatch($exchange, $data);
            if ($becameDecided) {
                $this->audit->log(
                    AuditAction::ExchangeClosed,
                    $ctx->user(),
                    $ctx->family,
                    ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                    entityType: 'Exchange',
                    entityId: (string) $exchange->getId(),
                ); // flush (commit)
            } else {
                $this->em->flush();
            }
        } catch (OptimisticLockException) {
            throw new ExchangeConflictException();
        }

        $this->publisher->publishExchange($exchange);
        if ($becameDecided) {
            $this->publisher->publishList($ctx->family);
            // Notification e-mail (Lot 7) : échange clôturé/tranché → membres habilités (hors auteur).
            $this->notifier->emit(NotificationType::ExchangeClosed, $ctx->family, $ctx->membership, (string) $exchange->getId(), [
                'exchangeId' => (string) $exchange->getId(),
                'title' => $exchange->getTitle(),
            ]);
        }

        return $this->assembler->detail($exchange, $tz);
    }
}
