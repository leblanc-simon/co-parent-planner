<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyPattern;
use App\State\Custody\CustodyPatternProvider;
use App\State\Custody\UpdatePatternProcessor;

/** Rythme de garde exposé (US-CUST-1). */
#[ApiResource(
    shortName: 'CustodyPattern',
    operations: [
        new Get(
            uriTemplate: '/families/{familyId}/custody/pattern',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            provider: CustodyPatternProvider::class,
        ),
        new Put(
            uriTemplate: '/families/{familyId}/custody/pattern',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            input: UpdatePatternRequest::class,
            read: false,
            processor: UpdatePatternProcessor::class,
        ),
    ],
)]
class CustodyPatternResource
{
    public string $id = '';
    public string $kind = '';
    public ?string $anchorDate = null;
    public int $handoverWeekday = 0;
    public string $handoverTime = '';
    /** @var list<string>|null */
    public ?array $customPattern = null;
    /** @var array<string, mixed>|null */
    public ?array $settings = null;
    public ?string $parentAMembershipId = null;
    public ?string $parentBMembershipId = null;

    public static function fromPattern(CustodyPattern $p): self
    {
        $dto = new self();
        $dto->id = (string) $p->getId();
        $dto->kind = $p->getKind()->value;
        $dto->anchorDate = $p->getAnchorDate()->format('Y-m-d');
        $dto->handoverWeekday = $p->getHandoverWeekday();
        $dto->handoverTime = $p->getHandoverTime();
        $dto->customPattern = $p->getCustomPattern();
        $dto->settings = $p->getSettings();
        $dto->parentAMembershipId = $p->getParentA() ? (string) $p->getParentA()->getId() : null;
        $dto->parentBMembershipId = $p->getParentB() ? (string) $p->getParentB()->getId() : null;

        return $dto;
    }
}
