<?php

declare(strict_types=1);

namespace App\Dto\Child;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\Entity\Child;
use App\State\Child\ChildItemProvider;
use App\State\Child\ChildrenProvider;
use App\State\Child\CreateChildProcessor;
use App\State\Child\DeleteChildProcessor;
use App\State\Child\UpdateChildProcessor;

/**
 * Profil enfant exposé (US-CH-1/4/5). `age` est **calculé** (jamais stocké) au fuseau famille.
 */
#[ApiResource(
    shortName: 'Child',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/children',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: ChildrenProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/children',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateChildRequest::class,
            read: false,
            processor: CreateChildProcessor::class,
        ),
        new Get(uriTemplate: '/children/{id}', provider: ChildItemProvider::class),
        new Patch(
            uriTemplate: '/children/{id}',
            input: UpdateChildRequest::class,
            read: false,
            processor: UpdateChildProcessor::class,
        ),
        new Delete(uriTemplate: '/children/{id}', output: false, read: false, processor: DeleteChildProcessor::class),
    ],
)]
class ChildResource
{
    public string $id = '';
    public string $firstName = '';
    public string $color = '';
    public string $initial = '';
    public ?string $birthDate = null;
    public ?int $age = null;
    public ?string $schoolLabel = null;
    /** @var list<array{label: string, value: string}> */
    public array $info = [];
    /** Version (verrou optimiste) : à renvoyer en `expectedVersion` pour une édition concurrente sûre. */
    public int $version = 1;

    public static function fromChild(Child $child, ?int $age): self
    {
        $dto = new self();
        $dto->id = (string) $child->getId();
        $dto->firstName = $child->getFirstName();
        $dto->color = $child->getColor();
        $dto->initial = $child->getInitial();
        $dto->birthDate = $child->getBirthDate()?->format('Y-m-d');
        $dto->age = $age;
        $dto->schoolLabel = $child->getSchoolLabel();
        $dto->info = $child->getInfo();
        $dto->version = $child->getVersion();

        return $dto;
    }
}
