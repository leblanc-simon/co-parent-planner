<?php

declare(strict_types=1);

namespace App\State\Family;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Family\FamilyResource;
use App\Entity\User;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Détail d'une famille — uniquement si l'utilisateur courant en est membre actif (sinon 404,
 * anti-énumération de familles, cohérent avec le scope §3.6).
 *
 * @implements ProviderInterface<FamilyResource>
 */
final class FamilyItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?FamilyResource
    {
        $user = $this->security->getUser();
        $id = $uriVariables['id'] ?? null;
        if (!$user instanceof User || !\is_string($id) || !Uuid::isValid($id)) {
            return null;
        }

        $membership = $this->memberships->findActive($user, Uuid::fromString($id));

        return null !== $membership ? FamilyResource::fromMembership($membership) : null;
    }
}
