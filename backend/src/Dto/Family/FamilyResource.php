<?php

declare(strict_types=1);

namespace App\Dto\Family;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\Membership;
use App\State\Family\CreateFamilyProcessor;
use App\State\Family\FamilyItemProvider;
use App\State\Family\MyFamiliesProvider;
use App\State\Family\UpdateFamilyProcessor;

/**
 * Représentation d'une famille du point de vue du membre courant (US-ONB-1, US-HOME-1).
 * `role`/`isAdmin` reflètent la perspective de l'utilisateur connecté dans cette famille.
 */
#[ApiResource(
    shortName: 'Family',
    operations: [
        new GetCollection(
            uriTemplate: '/families',
            paginationEnabled: false,
            provider: MyFamiliesProvider::class,
        ),
        new Get(
            uriTemplate: '/families/{id}',
            provider: FamilyItemProvider::class,
        ),
        new Post(
            uriTemplate: '/families',
            status: 201,
            input: CreateFamilyRequest::class,
            processor: CreateFamilyProcessor::class,
        ),
        new Patch(
            uriTemplate: '/families/{id}',
            input: UpdateFamilyRequest::class,
            read: false,
            processor: UpdateFamilyProcessor::class,
        ),
    ],
)]
class FamilyResource
{
    public string $id = '';
    public string $name = '';
    public string $type = '';
    public string $timezone = '';
    public ?string $schoolZone = null;
    public ?string $academy = null;
    public bool $expensesEnabled = false;
    public bool $hasCustody = false;
    public ?int $handoverWeekday = null;
    public ?string $handoverTime = null;
    public string $role = '';
    public bool $isAdmin = false;

    public static function fromMembership(Membership $membership): self
    {
        $family = $membership->getFamily();
        $settings = $family->getSettings();

        $dto = new self();
        $dto->id = (string) $family->getId();
        $dto->name = $family->getName();
        $dto->type = $family->getType()->value;
        $dto->timezone = $family->getTimezone();
        $dto->schoolZone = $family->getSchoolZone();
        $dto->academy = $family->getAcademy();
        $dto->expensesEnabled = $family->isExpensesEnabled();
        $dto->hasCustody = $family->hasCustody();
        $dto->handoverWeekday = \is_int($settings['handover_weekday'] ?? null) ? $settings['handover_weekday'] : null;
        $dto->handoverTime = \is_string($settings['handover_time'] ?? null) ? $settings['handover_time'] : null;
        $dto->role = $membership->getRole()->value;
        $dto->isAdmin = $membership->isAdmin();

        return $dto;
    }
}
