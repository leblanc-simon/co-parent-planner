<?php

declare(strict_types=1);

namespace App\State\FamilyLink;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\FamilyLink\FamilyLinkResource;
use App\Repository\FamilyLinkRepository;
use App\Security\FamilyScope;

/**
 * US-LINK-3 — liste les familles liées à la famille active (tout membre actif). Nom + statut
 * uniquement ; aucune donnée interne de l'autre famille (pas d'overlay au Lot 1).
 *
 * @implements ProviderInterface<FamilyLinkResource>
 */
final class FamilyLinksProvider implements ProviderInterface
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly FamilyLinkRepository $links,
    ) {
    }

    /**
     * @return list<FamilyLinkResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->scope->require();

        return array_map(
            static fn ($link) => FamilyLinkResource::fromLink($link, $ctx->family),
            $this->links->findForFamily($ctx->family),
        );
    }
}
