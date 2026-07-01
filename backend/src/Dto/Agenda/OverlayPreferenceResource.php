<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Dto\Family\FamilyResource;
use App\Entity\OverlayPreference;
use App\State\Agenda\OverlayPreferencesProvider;
use App\State\Agenda\UpdateOverlayPreferenceProcessor;

/**
 * Préférence d'overlay du **membre courant** (US-LINK-1, Lot 5 §6.2), par lien et par vue.
 * `GET` liste ses préférences ; `PUT` fait un upsert idempotent `{familyLinkId, view, enabled}`.
 */
#[ApiResource(
    shortName: 'OverlayPreference',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/overlay-preferences',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: OverlayPreferencesProvider::class,
        ),
        new Put(
            uriTemplate: '/families/{familyId}/overlay-preferences',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            input: UpdateOverlayPreferenceRequest::class,
            read: false,
            processor: UpdateOverlayPreferenceProcessor::class,
        ),
    ],
)]
class OverlayPreferenceResource
{
    public string $familyLinkId = '';
    public string $view = '';
    public bool $enabled = false;

    public static function fromEntity(OverlayPreference $preference): self
    {
        $dto = new self();
        $dto->familyLinkId = (string) $preference->getFamilyLink()->getId();
        $dto->view = $preference->getView()->value;
        $dto->enabled = $preference->isEnabled();

        return $dto;
    }
}
