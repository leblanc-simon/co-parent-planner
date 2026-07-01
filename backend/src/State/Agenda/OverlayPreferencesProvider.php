<?php

declare(strict_types=1);

namespace App\State\Agenda;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Agenda\OverlayPreferenceResource;
use App\Entity\OverlayPreference;
use App\Repository\OverlayPreferenceRepository;
use App\Security\Agenda\AgendaAccess;

/**
 * US-LINK-1 — préférences overlay du **membre courant** pour la famille active (§6.2). Scopé au
 * membre : jamais les préférences d'un autre membre.
 *
 * @implements ProviderInterface<OverlayPreferenceResource>
 */
final class OverlayPreferencesProvider implements ProviderInterface
{
    public function __construct(
        private readonly AgendaAccess $access,
        private readonly OverlayPreferenceRepository $preferences,
    ) {
    }

    /**
     * @return list<OverlayPreferenceResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();

        return array_map(
            static fn (OverlayPreference $p): OverlayPreferenceResource => OverlayPreferenceResource::fromEntity($p),
            $this->preferences->findByMembership($ctx->membership),
        );
    }
}
