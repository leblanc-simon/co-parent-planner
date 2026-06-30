<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Invitation\InvitationResource;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\InvitationRepository;
use App\Security\FamilyScope;

/**
 * US-INV-2 — liste les invitations `pending` de la famille active (réservé admin). Jamais de token.
 *
 * @implements ProviderInterface<InvitationResource>
 */
final class InvitationsProvider implements ProviderInterface
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly InvitationRepository $invitations,
    ) {
    }

    /**
     * @return list<InvitationResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        return array_map(
            static fn ($invitation) => InvitationResource::fromInvitation($invitation),
            $this->invitations->findPendingForFamily($ctx->family),
        );
    }
}
