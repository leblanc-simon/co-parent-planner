<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Dto\Family\FamilyResource;
use App\Entity\SchoolHoliday;
use App\State\Custody\CreateHolidayProcessor;
use App\State\Custody\ImportHolidaysProcessor;
use App\State\Custody\SchoolHolidaysProviderState;
use App\State\Custody\UpdateRuleProcessor;

/** Période de vacances exposée (US-CUST-3) avec sa règle éventuelle. */
#[ApiResource(
    shortName: 'SchoolHoliday',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/holidays',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: SchoolHolidaysProviderState::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/holidays',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateHolidayRequest::class,
            read: false,
            processor: CreateHolidayProcessor::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/holidays/import',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 202,
            input: false,
            output: false,
            read: false,
            deserialize: false,
            processor: ImportHolidaysProcessor::class,
        ),
        new Put(
            uriTemplate: '/holidays/{id}/rule',
            input: UpdateRuleRequest::class,
            read: false,
            processor: UpdateRuleProcessor::class,
        ),
    ],
)]
class SchoolHolidayResource
{
    public string $id = '';
    public string $label = '';
    public string $startDate = '';
    public string $endDate = '';
    public ?string $zone = null;
    public ?string $academy = null;
    public ?string $schoolYear = null;
    public string $source = '';
    public ?string $ruleKind = null;
    /** @var array<string, mixed>|null */
    public ?array $ruleParams = null;

    public static function fromHoliday(SchoolHoliday $h): self
    {
        $dto = new self();
        $dto->id = (string) $h->getId();
        $dto->label = $h->getLabel();
        $dto->startDate = $h->getStartDate()->format('Y-m-d');
        $dto->endDate = $h->getEndDate()->format('Y-m-d');
        $dto->zone = $h->getZone();
        $dto->academy = $h->getAcademy();
        $dto->schoolYear = $h->getSchoolYear();
        $dto->source = $h->getSource()->value;
        $rule = $h->getRule();
        $dto->ruleKind = $rule?->getKind()->value;
        $dto->ruleParams = $rule?->getParams();

        return $dto;
    }
}
