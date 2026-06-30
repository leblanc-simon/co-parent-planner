<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invitation;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\InvitationNotFoundException;
use App\Repository\InvitationRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-INV-2 — révoque une invitation `pending` (réservé admin). Idempotent si déjà non-pending.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class RevokeInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly InvitationRepository $invitations,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $invitation = $this->load($uriVariables['id'] ?? null, $ctx->family);

        if (InvitationStatus::Pending === $invitation->getStatus()) {
            $invitation->setStatus(InvitationStatus::Revoked);
        }

        $this->audit->log(
            AuditAction::InvitationRevoked,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Invitation',
            entityId: (string) $invitation->getId(),
        ); // flush

        return null;
    }

    private function load(mixed $id, \App\Entity\Family $family): Invitation
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new InvitationNotFoundException();
        }
        $invitation = $this->invitations->find(Uuid::fromString($id));
        if (!$invitation instanceof Invitation || !$invitation->getFamily()->getId()->equals($family->getId())) {
            throw new InvitationNotFoundException();
        }

        return $invitation;
    }
}
