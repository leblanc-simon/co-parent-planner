<?php

declare(strict_types=1);

namespace App\State\Device;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Device\DeviceResource;
use App\Entity\DeviceToken;
use App\Entity\User;
use App\Repository\DeviceTokenRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * US-PUSH-3 — appareils **actifs** du compte courant : ceux qui reçoivent encore des push, donc
 * ceux qu'on peut vouloir révoquer depuis un autre appareil.
 *
 * @implements ProviderInterface<DeviceResource>
 */
final class DevicesProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly DeviceTokenRepository $devices,
    ) {
    }

    /**
     * @return list<DeviceResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        return array_map(
            static fn (DeviceToken $device): DeviceResource => DeviceResource::fromDevice($device),
            $this->devices->findActiveForUser($user),
        );
    }
}
