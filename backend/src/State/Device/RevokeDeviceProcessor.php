<?php

declare(strict_types=1);

namespace App\State\Device;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DeviceToken;
use App\Entity\User;
use App\Exception\DeviceNotFoundException;
use App\Repository\DeviceTokenRepository;
use App\Service\Notification\Push\DeviceRegistrar;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-PUSH-3 — révoque un appareil du compte courant (déconnexion, appareil perdu). L'appareil d'un
 * autre compte répond 404 comme un appareil inexistant : l'identifiant ne permet pas de sonder.
 * Idempotent : révoquer un appareil déjà révoqué répond 204 sans rien changer.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class RevokeDeviceProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly DeviceTokenRepository $devices,
        private readonly DeviceRegistrar $registrar,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $id = $uriVariables['id'] ?? null;
        $device = \is_string($id) && Uuid::isValid($id) ? $this->devices->findOneOwnedBy($user, Uuid::fromString($id)) : null;
        if (!$device instanceof DeviceToken) {
            throw new DeviceNotFoundException();
        }
        if ($device->isActive()) {
            $this->registrar->revoke($device, $user, $this->requestStack->getCurrentRequest()?->getClientIp());
        }

        return null;
    }
}
