<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyDay;
use App\State\Custody\CustodyDaysProvider;

/** Jour de garde effectif exposé (US-CUST-5). */
#[ApiResource(
    shortName: 'CustodyDay',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/custody/days',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            // Fenêtre déclarée au contrat (revue C3) : sans elle, aucun client généré ne pouvait
            // demander autre chose que le mois courant.
            parameters: [
                // `pattern` plutôt que `format: date` : le générateur Dart mapperait
                // `format: date` sur `DateTime` (ISO 8601 complet), que `CustodyRange` refuse.
                'from' => new QueryParameter(
                    description: 'Début de la fenêtre (date locale, AAAA-MM-JJ). Défaut : 1er du mois courant.',
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
                'to' => new QueryParameter(
                    description: 'Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant.',
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
            ],
            provider: CustodyDaysProvider::class,
        ),
    ],
)]
class CustodyDayResource
{
    public string $date = '';
    public ?string $parentMembershipId = null;
    public string $source = '';

    public static function fromDay(CustodyDay $d): self
    {
        $dto = new self();
        $dto->date = $d->getDate()->format('Y-m-d');
        $dto->parentMembershipId = $d->getParentMembership() ? (string) $d->getParentMembership()->getId() : null;
        $dto->source = $d->getSource()->value;

        return $dto;
    }
}
