<?php

declare(strict_types=1);

namespace App\Security\Agenda;

use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Security\FamilyContext;
use App\Security\FamilyScope;

/**
 * Résolution + autorisation des ressources d'agenda (Lot 4 §7.1/§7.2). Scope X-Family-Id via
 * {@see FamilyScope}, Voter `agenda` par niveau. Contrairement à la garde, l'agenda **s'applique à
 * tous les types de famille** : aucune règle « famille unie » (§4.4).
 */
final class AgendaAccess
{
    public function __construct(private readonly FamilyScope $scope)
    {
    }

    public function context(): FamilyContext
    {
        return $this->scope->require();
    }

    /** Lecture : exige `agenda:view`. */
    public function read(): FamilyContext
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::Agenda, RightLevel::View);

        return $ctx;
    }

    /** Mutation : exige `agenda:edit`. */
    public function write(): FamilyContext
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::Agenda, RightLevel::Edit);

        return $ctx;
    }
}
