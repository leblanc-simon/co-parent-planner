<?php

declare(strict_types=1);

namespace App\Security\Exchanges;

use App\Entity\Exchange;
use App\Entity\Family;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\ExchangeNotFoundException;
use App\Repository\ExchangeRepository;
use App\Security\FamilyContext;
use App\Security\FamilyScope;
use Symfony\Component\Uid\Uuid;

/**
 * Résolution + autorisation des ressources d'échange (Lot 6 §7). Scope X-Family-Id via
 * {@see FamilyScope}, Voter `exchanges` par niveau. Les échanges s'appliquent à **tous les types de
 * famille** (séparée comme unie, §0.4). Un échange d'une autre famille est masqué en `404` (§4.4).
 */
final class ExchangeAccess
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly ExchangeRepository $exchanges,
    ) {
    }

    /** Lecture : exige `exchanges:view`. */
    public function read(): FamilyContext
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::Exchanges, RightLevel::View);

        return $ctx;
    }

    /** Mutation : exige `exchanges:edit`. */
    public function write(): FamilyContext
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::Exchanges, RightLevel::Edit);

        return $ctx;
    }

    /**
     * Charge un échange en vérifiant son appartenance à la famille scopée (sinon 404 non révélateur).
     */
    public function loadInFamily(mixed $id, Family $family): Exchange
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new ExchangeNotFoundException();
        }
        $exchange = $this->exchanges->find(Uuid::fromString($id));
        if (!$exchange instanceof Exchange || !$exchange->getFamily()->getId()->equals($family->getId())) {
            throw new ExchangeNotFoundException();
        }

        return $exchange;
    }
}
