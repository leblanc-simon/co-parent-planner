<?php

declare(strict_types=1);

namespace App\Dto\Device;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\DeviceToken;
use App\State\Device\DevicesProvider;
use App\State\Device\RegisterDeviceProcessor;
use App\State\Device\RevokeDeviceProcessor;

/**
 * Appareils du compte courant enregistrés pour le push (Lot 11 §6.2). Ressource **utilisateur** :
 * aucun `X-Family-Id` requis, et jamais l'appareil d'un autre compte (404, sans révéler qu'il existe).
 *
 * Le jeton n'est **jamais** renvoyé : il suffit à pousser du contenu vers l'appareil. La liste ne
 * montre que ce qui permet de reconnaître un appareil avant de le révoquer.
 */
#[ApiResource(
    shortName: 'Device',
    operations: [
        new GetCollection(
            uriTemplate: '/me/devices',
            paginationEnabled: false,
            provider: DevicesProvider::class,
        ),
        new Post(
            uriTemplate: '/me/devices',
            status: 201,
            input: RegisterDeviceRequest::class,
            read: false,
            processor: RegisterDeviceProcessor::class,
        ),
        new Delete(
            uriTemplate: '/me/devices/{id}',
            output: false,
            read: false,
            processor: RevokeDeviceProcessor::class,
        ),
    ],
)]
class DeviceResource
{
    public string $id = '';
    public string $platform = '';
    public string $status = '';
    public ?string $deviceModel = null;
    public ?string $appVersion = null;
    public ?string $lastSeenAt = null;
    public string $createdAt = '';

    public static function fromDevice(DeviceToken $device): self
    {
        $dto = new self();
        $dto->id = (string) $device->getId();
        $dto->platform = $device->getPlatform()->value;
        $dto->status = $device->getStatus()->value;
        $dto->deviceModel = $device->getDeviceModel();
        $dto->appVersion = $device->getAppVersion();
        $dto->lastSeenAt = $device->getLastSeenAt()?->format(\DATE_ATOM);
        $dto->createdAt = $device->getCreatedAt()->format(\DATE_ATOM);

        return $dto;
    }
}
