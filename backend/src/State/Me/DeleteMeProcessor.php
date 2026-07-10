<?php

declare(strict_types=1);

namespace App\State\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\RgpdOperation;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Repository\RgpdOperationRepository;
use App\Rgpd\Anonymizer;
use App\Security\Auth\AuditLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-SET-4b — effacement du compte courant (droit à l'effacement). Anonymisation préservant
 * l'intégrité des fils (Anonymizer), **refus si dernier admin** (409 via LastAdminProtectedException),
 * idempotent (compte déjà anonymisé → no-op). Trace une `RgpdOperation` (erasure) + AuditLog.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteMeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly Anonymizer $anonymizer,
        private readonly RgpdOperationRepository $operations,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
        $this->audit->log(
            AuditAction::RgpdErasureRequested,
            $user,
            ip: $ip,
            entityType: 'User',
            entityId: (string) $user->getId(),
        );

        // Lève LastAdminProtectedException (409) si le sujet est l'unique admin d'une famille.
        $this->anonymizer->anonymize($user, $user, $ip);

        $op = (new RgpdOperation(RgpdOperationType::Erasure))
            ->setMode(RgpdMode::Live)
            ->setStatus(RgpdOperationStatus::Done)
            ->setSubjectUser($user)
            ->setRequestedBy($user);
        $this->operations->save($op);

        $this->audit->log(
            AuditAction::RgpdErasureCompleted,
            $user,
            ip: $ip,
            entityType: 'User',
            entityId: (string) $user->getId(),
        );

        return null;
    }
}
