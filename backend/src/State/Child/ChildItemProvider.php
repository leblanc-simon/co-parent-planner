<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Child\ChildResource;
use App\Enum\ChildNotesAction;
use App\Security\Child\ChildAccess;
use App\Service\Child\AgeCalculator;

/**
 * US-CH-1 — détail d'un enfant, résolu strictement dans le scope famille (sinon 404).
 *
 * @implements ProviderInterface<ChildResource>
 */
final class ChildItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ChildAccess $access,
        private readonly AgeCalculator $age,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ChildResource
    {
        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['id'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::ChildView);

        return ChildResource::fromChild($child, $this->age->age($child->getBirthDate(), $ctx->family->getTimezone()));
    }
}
