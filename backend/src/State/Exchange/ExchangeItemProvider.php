<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Exchange\ExchangeResource;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangeAssembler;

/**
 * US-EX-5 — détail d'un échange (exchanges:view) : corps, options + décomptes, fil déchiffré, statut,
 * marqueur « en retard ». Scope X-Family-Id ; un échange d'une autre famille est masqué en 404.
 *
 * @implements ProviderInterface<ExchangeResource>
 */
final class ExchangeItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ExchangeAccess $access,
        private readonly ExchangeAssembler $assembler,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ExchangeResource
    {
        $ctx = $this->access->read();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $exchange = $this->access->loadInFamily($uriVariables['id'] ?? null, $ctx->family);

        return $this->assembler->detail($exchange, $tz);
    }
}
