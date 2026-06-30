<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Child\ChildResource;
use App\Enum\ChildNotesAction;
use App\Repository\ChildRepository;
use App\Security\Child\ChildAccess;
use App\Service\Child\AgeCalculator;

/**
 * US-CH-1 — liste les enfants de la famille active (droit `children_notes ≥ view`).
 *
 * @implements ProviderInterface<ChildResource>
 */
final class ChildrenProvider implements ProviderInterface
{
    public function __construct(
        private readonly ChildAccess $access,
        private readonly ChildRepository $children,
        private readonly AgeCalculator $age,
    ) {
    }

    /**
     * @return list<ChildResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->context();
        $this->access->authorize($ctx, ChildNotesAction::ChildView);

        $tz = $ctx->family->getTimezone();

        return array_map(
            fn ($child) => ChildResource::fromChild($child, $this->age->age($child->getBirthDate(), $tz)),
            $this->children->findByFamily($ctx->family),
        );
    }
}
