<?php

declare(strict_types=1);

namespace App\State\Member;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Member\MemberResource;
use App\Repository\MembershipRepository;
use App\Security\FamilyScope;

/**
 * US-MBR-1 — liste les membres actifs de la famille active (tout membre actif y a accès).
 * Les invitations en attente sont exposées séparément (GET /families/{id}/invitations, admin).
 *
 * @implements ProviderInterface<MemberResource>
 */
final class FamilyMembersProvider implements ProviderInterface
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly MembershipRepository $memberships,
    ) {
    }

    /**
     * @return list<MemberResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->scope->require();
        $currentUserId = (string) $ctx->user()->getId();

        return array_map(
            static fn ($membership) => MemberResource::fromMembership(
                $membership,
                self: (string) $membership->getUser()->getId() === $currentUserId,
            ),
            $this->memberships->findActiveByFamily($ctx->family),
        );
    }
}
