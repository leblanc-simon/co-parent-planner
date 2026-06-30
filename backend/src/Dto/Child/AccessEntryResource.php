<?php

declare(strict_types=1);

namespace App\Dto\Child;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Service\Child\AccessEntry;
use App\State\Child\ChildAccessProvider;

/**
 * Une entrée du bloc « Accès & droits » d'un enfant (US-CH-2) — sous-ressource dédiée (§5.4 #5).
 */
#[ApiResource(
    shortName: 'ChildAccess',
    operations: [
        new GetCollection(
            uriTemplate: '/children/{childId}/access',
            uriVariables: ['childId' => new Link(fromClass: ChildResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: ChildAccessProvider::class,
        ),
    ],
)]
class AccessEntryResource
{
    public string $membershipId = '';
    public string $name = '';
    public string $role = '';
    public string $level = '';
    public bool $isOwnProfile = false;
    public ?string $displayColor = null;

    public static function fromEntry(AccessEntry $entry): self
    {
        $dto = new self();
        $dto->membershipId = $entry->membershipId;
        $dto->name = $entry->name;
        $dto->role = $entry->role;
        $dto->level = $entry->level;
        $dto->isOwnProfile = $entry->isOwnProfile;
        $dto->displayColor = $entry->displayColor;

        return $dto;
    }
}
