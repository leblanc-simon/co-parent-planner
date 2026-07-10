<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
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
