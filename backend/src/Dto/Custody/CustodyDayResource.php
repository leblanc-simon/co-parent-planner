<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
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
