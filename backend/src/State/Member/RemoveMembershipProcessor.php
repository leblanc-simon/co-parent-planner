<?php

declare(strict_types=1);

namespace App\State\Member;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\AuditAction;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\MembershipNotFoundException;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use App\Security\Membership\LastAdminGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-MBR-3/4 — retire un membre (admin) ou auto-départ (non-admin). Protège le dernier admin
 * (retrait comme auto-départ). Suppression du membership + de ses droits (cascade).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class RemoveMembershipProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyScope $scope,
        private readonly MembershipRepository $memberships,
        private readonly LastAdminGuard $lastAdmin,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->scope->require();
        $membership = $this->loadInScope($uriVariables['id'] ?? null, $ctx->family);

        $isSelf = $membership->getUser()->getId()->equals($ctx->user()->getId());

        // Un non-membre-admin ne peut retirer autrui ; l'auto-départ est toujours permis (sauf dernier admin).
        if (!$isSelf) {
            $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);
        }

        $membershipId = (string) $membership->getId();
        $action = $isSelf ? AuditAction::MembershipLeft : AuditAction::MembershipRemoved;
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        // Mutation + audit committés atomiquement (pas de trou append-only si le flush échoue).
        $this->em->wrapInTransaction(function () use ($membership, $membershipId, $action, $ip, $ctx): void {
            $this->lastAdmin->assertNotLastAdmin($membership);
            $this->em->remove($membership);
            $this->audit->persist(
                $action, $ctx->user(), $ctx->family, ip: $ip,
                metadata: ['membership' => $membershipId], entityType: 'Membership', entityId: $membershipId,
            );
        });

        return null;
    }

    private function loadInScope(mixed $id, Family $family): Membership
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new MembershipNotFoundException();
        }
        $membership = $this->memberships->find(Uuid::fromString($id));
        if (!$membership instanceof Membership || !$membership->getFamily()->getId()->equals($family->getId())) {
            throw new MembershipNotFoundException();
        }

        return $membership;
    }
}
