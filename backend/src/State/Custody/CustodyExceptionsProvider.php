<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\CustodyExceptionResource;
use App\Repository\CustodyExceptionRepository;
use App\Security\Custody\CustodyAccess;

/**
 * US-CUST-4 — liste les exceptions de la famille (custody:view, famille séparée).
 *
 * @implements ProviderInterface<CustodyExceptionResource>
 */
final class CustodyExceptionsProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly CustodyExceptionRepository $exceptions,
    ) {
    }

    /**
     * @return list<CustodyExceptionResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();

        return array_map(
            static fn ($e) => CustodyExceptionResource::fromException($e),
            $this->exceptions->findByFamily($ctx->family),
        );
    }
}
