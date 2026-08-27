<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\CustodyStatsResource;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\CustodyStatsCalculator;

/**
 * US-CUST-5 — statistiques de jours par parent sur [from, to] (calcul à la volée).
 *
 * @implements ProviderInterface<CustodyStatsResource>
 */
final class CustodyStatsProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly CustodyStatsCalculator $calculator,
        private readonly CustodyRange $range,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustodyStatsResource
    {
        $ctx = $this->access->read();
        [$from, $to] = $this->range->resolve();
        $stats = $this->calculator->compute($ctx->family, $from, $to);

        $dto = new CustodyStatsResource();
        $dto->parentACount = $stats['parentACount'];
        $dto->parentBCount = $stats['parentBCount'];
        $dto->otherCount = $stats['otherCount'];
        $dto->total = $stats['total'];
        $dto->daysInWindow = $stats['daysInWindow'];
        $dto->pctA = $stats['pctA'];
        $dto->pctB = $stats['pctB'];
        $dto->from = $from->format('Y-m-d');
        $dto->to = $to->format('Y-m-d');

        return $dto;
    }
}
