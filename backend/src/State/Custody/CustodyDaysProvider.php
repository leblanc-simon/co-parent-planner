<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\CustodyDayResource;
use App\Repository\CustodyDayRepository;
use App\Security\Custody\CustodyAccess;

/**
 * US-CUST-5 — jours effectifs sur la fenêtre [from, to] (famille séparée + custody:view).
 *
 * @implements ProviderInterface<CustodyDayResource>
 */
final class CustodyDaysProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly CustodyDayRepository $days,
        private readonly CustodyRange $range,
    ) {
    }

    /**
     * @return list<CustodyDayResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();
        [$from, $to] = $this->range->resolve();

        return array_map(
            static fn ($day) => CustodyDayResource::fromDay($day),
            $this->days->findRange($ctx->family, $from, $to),
        );
    }
}
