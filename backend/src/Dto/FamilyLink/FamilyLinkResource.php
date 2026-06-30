<?php

declare(strict_types=1);

namespace App\Dto\FamilyLink;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\State\FamilyLink\ActivateLinkProcessor;
use App\State\FamilyLink\CreateLinkProcessor;
use App\State\FamilyLink\FamilyLinksProvider;
use App\State\FamilyLink\RemoveLinkProcessor;

/**
 * Lien entre familles, vu depuis la famille active (US-LINK-1/2/3). N'expose **que** l'identité et
 * le statut de l'autre famille — **aucune** donnée interne (membres, enfants, agenda). Pas d'overlay.
 */
#[ApiResource(
    shortName: 'FamilyLink',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/links',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: FamilyLinksProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/links',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateLinkRequest::class,
            read: false,
            processor: CreateLinkProcessor::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/links/{linkId}/activate',
            status: 200,
            uriVariables: [
                'familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id']),
                'linkId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            read: false,
            deserialize: false,
            processor: ActivateLinkProcessor::class,
        ),
        new Delete(
            uriTemplate: '/families/{familyId}/links/{linkId}',
            uriVariables: [
                'familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id']),
                'linkId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            output: false,
            read: false,
            processor: RemoveLinkProcessor::class,
        ),
    ],
)]
class FamilyLinkResource
{
    public string $id = '';
    public string $status = '';
    public string $otherFamilyId = '';
    public string $otherFamilyName = '';
    /** Vrai si la famille active est à l'origine du lien (sinon elle l'a reçu). */
    public bool $initiatedByMe = false;

    public static function fromLink(FamilyLink $link, Family $viewpoint): self
    {
        $other = $link->otherFamily($viewpoint);

        $dto = new self();
        $dto->id = (string) $link->getId();
        $dto->status = $link->getStatus()->value;
        $dto->otherFamilyId = (string) $other->getId();
        $dto->otherFamilyName = $other->getName();
        $dto->initiatedByMe = $link->getFamily()->getId()->equals($viewpoint->getId());

        return $dto;
    }
}
