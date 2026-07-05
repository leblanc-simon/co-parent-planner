<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Exchange\ExchangeResource;
use App\Enum\ExchangeStatus;
use App\Exception\ExchangeInputException;
use App\Repository\ExchangeRepository;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangeAssembler;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-EX-4 — liste les échanges de la famille active (exchanges:view), filtrés par `?status=`, triés
 * (§3.6). Scope X-Family-Id. Renvoie des résumés (sans le fil).
 *
 * @implements ProviderInterface<ExchangeResource>
 */
final class ExchangesProvider implements ProviderInterface
{
    public function __construct(
        private readonly ExchangeAccess $access,
        private readonly ExchangeRepository $exchanges,
        private readonly ExchangeAssembler $assembler,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<ExchangeResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $list = $this->exchanges->listForFamily($ctx->family, $this->statusFilter());

        return $this->assembler->list($list, $tz);
    }

    private function statusFilter(): ?ExchangeStatus
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get('status');
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }
        $status = ExchangeStatus::tryFrom($raw);
        if (null === $status) {
            throw new ExchangeInputException('exchange.error.status_invalid');
        }

        return $status;
    }
}
