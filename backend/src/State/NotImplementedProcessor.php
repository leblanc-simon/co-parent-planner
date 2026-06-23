<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Exception\NotImplementedException;

/**
 * Processor des endpoints routés mais non implémentés (POST/DELETE) → 501 (décision D7).
 *
 * @implements ProcessorInterface<mixed, never>
 */
final class NotImplementedProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): never
    {
        throw new NotImplementedException();
    }
}
