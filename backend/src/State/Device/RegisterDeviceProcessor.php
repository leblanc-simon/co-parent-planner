<?php

declare(strict_types=1);

namespace App\State\Device;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Device\DeviceResource;
use App\Dto\Device\RegisterDeviceRequest;
use App\Entity\User;
use App\Service\Notification\Push\DeviceRegistrar;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-PUSH-1/2 — enregistre ou rafraîchit l'appareil courant. Idempotent par valeur de jeton : un
 * rejeu renvoie l'enregistrement existant (201 dans les deux cas, le Lot 11 §2 l'autorise), et
 * l'outbox mobile peut en plus poser un `Idempotency-Key`.
 *
 * @implements ProcessorInterface<RegisterDeviceRequest, DeviceResource>
 */
final class RegisterDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly DeviceRegistrar $registrar,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeviceResource
    {
        \assert($data instanceof RegisterDeviceRequest);
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $device = $this->registrar->register($user, $data, $this->requestStack->getCurrentRequest()?->getClientIp());

        return DeviceResource::fromDevice($device);
    }
}
