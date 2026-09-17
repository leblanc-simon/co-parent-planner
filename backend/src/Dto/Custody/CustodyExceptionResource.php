<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyException;
use App\State\Custody\CreateExceptionProcessor;
use App\State\Custody\CustodyExceptionsProvider;
use App\State\Custody\DeleteExceptionProcessor;

/** Exception de garde exposée (US-CUST-4). */
#[ApiResource(
    shortName: 'CustodyException',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/custody/exceptions',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: CustodyExceptionsProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/custody/exceptions',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateExceptionRequest::class,
            read: false,
            processor: CreateExceptionProcessor::class,
        ),
        new Delete(
            uriTemplate: '/families/{familyId}/custody/exceptions/{exceptionId}',
            uriVariables: [
                'familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id']),
                'exceptionId' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            output: false,
            read: false,
            processor: DeleteExceptionProcessor::class,
        ),
    ],
)]
class CustodyExceptionResource
{
    public string $id = '';
    public string $startDate = '';
    public string $endDate = '';
    /** Bornes horaires (HH:MM) ; `null` des deux côtés = journées entières. */
    public ?string $startTime = null;
    public ?string $endTime = null;
    public ?string $parentMembershipId = null;
    public ?string $reason = null;

    public static function fromException(CustodyException $e): self
    {
        $dto = new self();
        $dto->id = (string) $e->getId();
        $dto->startDate = $e->getStartDate()->format('Y-m-d');
        $dto->endDate = $e->getEndDate()->format('Y-m-d');
        $dto->startTime = $e->getStartTime();
        $dto->endTime = $e->getEndTime();
        $dto->parentMembershipId = $e->getParentMembership() ? (string) $e->getParentMembership()->getId() : null;
        $dto->reason = $e->getReason();

        return $dto;
    }
}
