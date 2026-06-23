<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\State\NotImplementedProcessor;
use App\State\NotImplementedProvider;

/**
 * Endpoints d'administration RGPD (cdc §6.2). Routés et présents dans l'OpenAPI mais **501**
 * jusqu'au Lot 8 (décision D7). Auth technique renforcée et logique réelles = Lot 8.
 */
#[ApiResource(
    shortName: 'RgpdAdmin',
    operations: [
        new GetCollection(uriTemplate: '/admin/rgpd/register', paginationEnabled: false, output: false, provider: NotImplementedProvider::class),
        new GetCollection(uriTemplate: '/admin/rgpd/operations', paginationEnabled: false, output: false, provider: NotImplementedProvider::class),
        new GetCollection(uriTemplate: '/admin/rgpd/compliance-report', paginationEnabled: false, output: false, provider: NotImplementedProvider::class),
        new Post(uriTemplate: '/admin/rgpd/purge', output: false, read: false, deserialize: false, processor: NotImplementedProcessor::class),
    ],
)]
final class RgpdStub
{
}
