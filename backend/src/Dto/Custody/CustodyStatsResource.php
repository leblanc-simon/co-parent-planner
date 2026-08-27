<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\Family\FamilyResource;
use App\State\Custody\CustodyStatsProvider;

/** Statistiques de garde sur une fenêtre (US-CUST-5). */
#[ApiResource(
    shortName: 'CustodyStats',
    operations: [
        new Get(
            uriTemplate: '/families/{familyId}/custody/stats',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            parameters: [
                'from' => new QueryParameter(
                    description: 'Début de la fenêtre (date locale, AAAA-MM-JJ). Défaut : 1er du mois courant.',
                    schema: ['type' => 'string', 'format' => 'date'],
                ),
                'to' => new QueryParameter(
                    description: 'Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant.',
                    schema: ['type' => 'string', 'format' => 'date'],
                ),
            ],
            provider: CustodyStatsProvider::class,
        ),
    ],
)]
class CustodyStatsResource
{
    public string $id = 'stats';
    public int $parentACount = 0;
    public int $parentBCount = 0;
    public int $otherCount = 0;
    public int $total = 0;
    /** Jours calendaires de la fenêtre ; `total` < `daysInWindow` = fenêtre partiellement générée. */
    public int $daysInWindow = 0;
    public float $pctA = 0.0;
    public float $pctB = 0.0;
    public ?string $from = null;
    public ?string $to = null;
}
