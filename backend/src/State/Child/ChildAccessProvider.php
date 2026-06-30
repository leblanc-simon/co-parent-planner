<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Child\AccessEntryResource;
use App\Enum\ChildNotesAction;
use App\Security\Child\ChildAccess;
use App\Service\Child\AccessRightsBuilder;

/**
 * US-CH-2 — bloc « Accès & droits » d'un enfant (droit `children_notes ≥ view`).
 *
 * @implements ProviderInterface<AccessEntryResource>
 */
final class ChildAccessProvider implements ProviderInterface
{
    public function __construct(
        private readonly ChildAccess $access,
        private readonly AccessRightsBuilder $builder,
    ) {
    }

    /**
     * @return list<AccessEntryResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['childId'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::ChildView);

        return array_map(
            static fn ($entry) => AccessEntryResource::fromEntry($entry),
            $this->builder->build($child),
        );
    }
}
