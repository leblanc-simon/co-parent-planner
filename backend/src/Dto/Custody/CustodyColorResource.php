<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyColorPreference;
use App\Enum\CustodyPalette;
use App\State\Custody\CustodyColorsProvider;
use App\State\Custody\UpdateCustodyColorProcessor;

/**
 * Couleur du planning choisie par le **membre courant** pour un parent donné (cdc §8.2).
 *
 * `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{targetMembershipId, palette}`.
 * Même forme que `OverlayPreference` (Lot 5) : c'est la même nature de donnée — une préférence
 * d'affichage locale au spectateur, qui n'engage personne d'autre.
 */
#[ApiResource(
    shortName: 'CustodyColor',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/custody-colors',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: CustodyColorsProvider::class,
        ),
        new Put(
            uriTemplate: '/families/{familyId}/custody-colors',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            input: UpdateCustodyColorRequest::class,
            read: false,
            processor: UpdateCustodyColorProcessor::class,
        ),
    ],
)]
class CustodyColorResource
{
    public string $targetMembershipId = '';
    public string $palette = '';
    /** Triplet effectif, pour que le client n'ait pas à recopier la table du §8.2. */
    public string $border = '';
    public string $soft = '';
    public string $ink = '';

    public static function fromEntity(CustodyColorPreference $preference): self
    {
        return self::of((string) $preference->getTarget()->getId(), $preference->getPalette());
    }

    public static function of(string $targetMembershipId, CustodyPalette $palette): self
    {
        $colors = $palette->colors();

        $dto = new self();
        $dto->targetMembershipId = $targetMembershipId;
        $dto->palette = $palette->value;
        $dto->border = $colors['border'];
        $dto->soft = $colors['soft'];
        $dto->ink = $colors['ink'];

        return $dto;
    }
}
