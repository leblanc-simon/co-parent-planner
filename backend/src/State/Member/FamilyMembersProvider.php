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
        $members = $this->memberships->findActiveByFamily($ctx->family);

        // Le couple (E1) n'est stocké que du côté du beau-parent : l'index miroir permet de le rendre
        // AUSSI du côté du parent, en une passe sur la collection déjà chargée — plutôt qu'une
        // requête par membre, ou une jointure que chaque écran referait de son côté.
        $partnerOf = [];
        foreach ($members as $member) {
            $partner = $member->getPartnerMembership();
            if (null !== $partner) {
                $partnerOf[(string) $member->getId()] = $partner;
                $partnerOf[(string) $partner->getId()] = $member;
            }
        }

        return array_map(
            static fn ($membership) => MemberResource::fromMembership(
                $membership,
                self: (string) $membership->getUser()->getId() === $currentUserId,
                partner: $partnerOf[(string) $membership->getId()] ?? null,
            ),
            $members,
        );
    }
}
