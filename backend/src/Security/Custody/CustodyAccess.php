<?php

declare(strict_types=1);

namespace App\Security\Custody;

use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\CustodyNotApplicableException;
use App\Exception\CustodyNotFoundException;
use App\Security\FamilyContext;
use App\Security\FamilyScope;

/**
 * Résolution + autorisation des ressources de garde (Lot 3). Scope X-Family-Id (FamilyScope),
 * convention famille unie figée (lecture → 404, mutation → 409), Voter `custody` par niveau.
 */
final class CustodyAccess
{
    public function __construct(private readonly FamilyScope $scope)
    {
    }

    /** Contexte famille active (header X-Family-Id validé). */
    public function context(): FamilyContext
    {
        return $this->scope->require();
    }

    /** Lecture : une famille unie masque la garde (404 non révélateur). */
    public function read(): FamilyContext
    {
        $ctx = $this->scope->require();
        if (!$ctx->family->getType()->hasCustody()) {
            throw new CustodyNotFoundException();
        }
        $this->scope->denyUnless($ctx, RightDomain::Custody, RightLevel::View);

        return $ctx;
    }

    /** Mutation : une famille unie interdit la garde (409 conflit d'état), puis exige custody:edit. */
    public function write(): FamilyContext
    {
        $ctx = $this->scope->require();
        if (!$ctx->family->getType()->hasCustody()) {
            throw new CustodyNotApplicableException();
        }
        $this->scope->denyUnless($ctx, RightDomain::Custody, RightLevel::Edit);

        return $ctx;
    }
}
