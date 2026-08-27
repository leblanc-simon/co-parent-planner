<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\Rgpd\OperationsProvider;

/**
 * Une opération du journal RGPD (US-RGPD-2), lecture seule. `GET /admin/rgpd/operations`
 * (admin technique + step-up), filtrable par `type`/`status`, paginé (`page`, `itemsPerPage`).
 */
#[ApiResource(
    shortName: 'RgpdOperationLog',
    operations: [
        new GetCollection(
            uriTemplate: '/admin/rgpd/operations',
            paginationEnabled: false,
            parameters: [
                'type' => new QueryParameter(description: "Filtre de type d'opération RGPD.", schema: ['type' => 'string']),
                'status' => new QueryParameter(description: 'Filtre de statut.', schema: ['type' => 'string']),
                'page' => new QueryParameter(description: 'Page (1 par défaut).', schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1]),
                'itemsPerPage' => new QueryParameter(description: 'Taille de page (50 par défaut, 200 maximum).', schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50]),
            ],
            provider: OperationsProvider::class,
        ),
    ],
)]
class RgpdOperationView
{
    public string $id = '';
    public string $type = '';
    public string $status = '';
    public string $mode = '';
    public ?string $subjectUserId = null;
    public ?string $requestedById = null;
    public ?string $resultRef = null;
    public string $occurredAt = '';
}
