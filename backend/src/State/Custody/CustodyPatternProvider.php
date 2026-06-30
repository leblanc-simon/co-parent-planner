<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\CustodyPatternResource;
use App\Exception\CustodyNotFoundException;
use App\Repository\CustodyPatternRepository;
use App\Security\Custody\CustodyAccess;

/**
 * GET du rythme de garde (US-CUST-1) — famille séparée + `custody:view`. 404 si aucun rythme.
 *
 * @implements ProviderInterface<CustodyPatternResource>
 */
final class CustodyPatternProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly CustodyPatternRepository $patterns,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustodyPatternResource
    {
        $ctx = $this->access->read();
        $pattern = $this->patterns->findOneByFamily($ctx->family);
        if (null === $pattern) {
            throw new CustodyNotFoundException();
        }

        return CustodyPatternResource::fromPattern($pattern);
    }
}
