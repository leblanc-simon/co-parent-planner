<?php

declare(strict_types=1);

namespace App\State\Me;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Me;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Fournit le profil `Me` de l'utilisateur authentifié (jamais un autre compte).
 *
 * @implements ProviderInterface<Me>
 */
final class MeProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Me
    {
        $user = $this->security->getUser();

        return $user instanceof User ? Me::fromUser($user) : null;
    }
}
