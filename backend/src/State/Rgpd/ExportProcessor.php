<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Rgpd\ExportResource;
use App\Entity\RgpdOperation;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationType;
use App\Message\ExportUserData;
use App\Repository\RgpdOperationRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\TooManyAttemptsException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * US-SET-4 — déclenche un export RGPD **asynchrone** (droit d'accès / portabilité). Crée la
 * `RgpdOperation` (pending), journalise, dispatche le job d'assemblage et répond 202. Rate-limité
 * par utilisateur (anti-abus de génération d'archives).
 *
 * @implements ProcessorInterface<mixed, ExportResource>
 */
final class ExportProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RgpdOperationRepository $operations,
        private readonly Security $security,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $rgpdExportLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExportResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $limit = $this->rgpdExportLimiter->create((string) $user->getId())->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - Clock::get()->now()->getTimestamp());
        }

        $op = (new RgpdOperation(RgpdOperationType::Export))
            ->setMode(RgpdMode::Live)
            ->setSubjectUser($user)
            ->setRequestedBy($user);
        $this->operations->save($op);

        $this->audit->log(
            AuditAction::RgpdExportRequested,
            $user,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'RgpdOperation',
            entityId: (string) $op->getId(),
        );

        $this->bus->dispatch(new ExportUserData((string) $op->getId(), (string) $user->getId()));

        return new ExportResource((string) $op->getId(), $op->getStatus()->value);
    }
}
