<?php

declare(strict_types=1);

namespace App\State\Family;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Family\FamilyResource;
use App\Entity\User;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Liste les familles de l'utilisateur courant via ses memberships actifs (US-ONB-1, sélecteur de famille).
 *
 * @implements ProviderInterface<FamilyResource>
 */
final class MyFamiliesProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
    ) {
    }

    /**
     * @return list<FamilyResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_map(
            static fn ($membership) => FamilyResource::fromMembership($membership),
            $this->memberships->findActiveForUser($user),
        );
    }
}
