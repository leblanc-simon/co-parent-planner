<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\SchoolHolidayResource;
use App\Repository\SchoolHolidayRepository;
use App\Security\Custody\CustodyAccess;

/**
 * US-CUST-3 — liste les périodes (officielles + manuelles) de la famille (custody:view, séparée).
 *
 * @implements ProviderInterface<SchoolHolidayResource>
 */
final class SchoolHolidaysProviderState implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly SchoolHolidayRepository $holidays,
    ) {
    }

    /**
     * @return list<SchoolHolidayResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();

        return array_map(
            static fn ($h) => SchoolHolidayResource::fromHoliday($h),
            $this->holidays->findByFamily($ctx->family),
        );
    }
}
