<?php

declare(strict_types=1);

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Realtime\RealtimeTokenProvider;

/**
 * Jeton Mercure scopé par famille active (cdc §4.5, §3.12). Distinct du JWT d'accès API ; émis
 * par le serveur (seule autorité sur `subscribe`). Au Lot 0, `subscribe` est **vide** (aucun topic
 * métier) ; recalculé à chaque bascule de famille (nouveau X-Family-Id).
 */
#[ApiResource(
    shortName: 'RealtimeToken',
    operations: [
        new Get(uriTemplate: '/realtime/token', provider: RealtimeTokenProvider::class),
    ],
)]
final class RealtimeToken
{
    public function __construct(
        public string $token = '',
        public string $hub = '',
    ) {
    }
}
