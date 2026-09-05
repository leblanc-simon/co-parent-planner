<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Custody\CustodyColorResource;
use App\Repository\CustodyColorPreferenceRepository;
use App\Security\Custody\CustodyAccess;

/**
 * Couleurs de planning du **membre courant** (cdc §8.2).
 *
 * Lecture sous `custody ≥ view` : choisir ses couleurs est une préférence personnelle, offerte à
 * quiconque **voit** le planning — un beau-parent en lecture comprise. C'est délibérément moins
 * exigeant que les autres écritures de la garde, qui demandent `custody:edit`.
 *
 * @implements ProviderInterface<CustodyColorResource>
 */
final class CustodyColorsProvider implements ProviderInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly CustodyColorPreferenceRepository $preferences,
    ) {
    }

    /**
     * @return list<CustodyColorResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();

        return array_map(
            static fn ($preference) => CustodyColorResource::fromEntity($preference),
            $this->preferences->findForViewer($ctx->membership),
        );
    }
}
