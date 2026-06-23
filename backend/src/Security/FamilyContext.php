<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\RightDomain;
use App\Enum\RightLevel;

/**
 * Contexte de la **famille active** résolu pour une requête (scope X-Family-Id — §3.6).
 * Porte la famille et le membership actif de l'utilisateur courant ; sert de base aux Voters.
 */
final readonly class FamilyContext
{
    public function __construct(
        public Family $family,
        public Membership $membership,
    ) {
    }

    public function user(): User
    {
        return $this->membership->getUser();
    }

    public function can(RightDomain $domain, RightLevel $required): bool
    {
        return $this->membership->can($domain, $required);
    }

    public function level(RightDomain $domain): RightLevel
    {
        return $this->membership->effectiveLevel($domain);
    }
}
