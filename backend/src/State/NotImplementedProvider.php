<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Exception\NotImplementedException;

/**
 * Provider des endpoints routés mais non implémentés (GET) → 501 (décision D7).
 *
 * @implements ProviderInterface<object>
 */
final class NotImplementedProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): never
    {
        throw new NotImplementedException();
    }
}
