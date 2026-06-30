<?php

declare(strict_types=1);

namespace App\Security\Membership;

use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;

/**
 * Normalise les droits proposés/demandés selon le rôle (§3.1, §3.7).
 *
 * Renvoie la **map des droits à matérialiser** (domaine → niveau, niveaux `none` omis) :
 * - **parent** : aucune ligne `Right` (résolution par rôle = source unique de vérité) ;
 * - **step_parent** : `family_settings` forcé `none` ; autres domaines tels quels (expenses reste
 *   inerte v1 via {@see \App\Entity\Membership::effectiveLevel()}) ;
 * - **child** : `expenses`/`family_settings` forcés `none`, autres domaines plafonnés à `view`.
 *
 * Les valeurs hors borne sont **normalisées** (jamais une erreur, cf. §2.4 « interdits enfant »).
 */
final class RightsNormalizer
{
    /**
     * @param array<string, string> $requested domaine → niveau demandé
     *
     * @return array<string, RightLevel> domaine → niveau retenu (sans les `none`)
     */
    public function normalize(MembershipRole $role, array $requested): array
    {
        if ($role->forcesEdit()) {
            return [];
        }

        $result = [];
        foreach (RightDomain::cases() as $domain) {
            $requestedLevel = RightLevel::tryFrom($requested[$domain->value] ?? '') ?? RightLevel::None;
            $level = $this->capForRole($role, $domain, $requestedLevel);
            if (RightLevel::None !== $level) {
                $result[$domain->value] = $level;
            }
        }

        return $result;
    }

    private function capForRole(MembershipRole $role, RightDomain $domain, RightLevel $level): RightLevel
    {
        // family_settings : jamais accordé à un step_parent / child (forcé none).
        if ($domain->isStructural()) {
            return RightLevel::None;
        }

        if ($role->isChild()) {
            if ($domain->isForbiddenForChild()) {
                return RightLevel::None; // expenses interdit à l'enfant
            }

            return $level->atMost(RightLevel::View); // jamais d'édition pour un enfant
        }

        // step_parent : niveau tel quel (expenses resté inerte v1 au niveau du résolveur).
        return $level;
    }
}
