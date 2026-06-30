<?php

declare(strict_types=1);

namespace App\Security\Membership;

use App\Entity\Membership;
use App\Entity\Right;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\AdminRequiresParentRoleException;

/**
 * Source unique d'écriture d'un Membership (§3.7) : valide l'invariant `is_admin ⇒ role=parent`
 * puis applique rôle, flag admin et **droits normalisés** (création de famille, acceptation
 * d'invitation, PATCH). Toute incohérence est **rejetée** (jamais corrigée en silence).
 */
final class MembershipWriter
{
    public function __construct(private readonly RightsNormalizer $rights)
    {
    }

    /**
     * @param array<string, string> $requestedRights domaine → niveau demandé
     *
     * @throws AdminRequiresParentRoleException si `is_admin=true` sur un rôle non-parent
     */
    public function apply(Membership $membership, MembershipRole $role, bool $isAdmin, array $requestedRights): void
    {
        if ($isAdmin && MembershipRole::Parent !== $role) {
            throw new AdminRequiresParentRoleException();
        }

        $membership->setRole($role)->setIsAdmin($isAdmin);
        $this->syncRights($membership, $this->rights->normalize($role, $requestedRights));
    }

    /**
     * Synchronise la matrice de droits matérialisés vers l'état normalisé cible
     * (suppression des domaines retombés à `none`, mise à jour/ajout des autres).
     *
     * @param array<string, RightLevel> $normalized
     */
    private function syncRights(Membership $membership, array $normalized): void
    {
        foreach ($membership->getRights()->toArray() as $right) {
            \assert($right instanceof Right);
            if (!isset($normalized[$right->getDomain()->value])) {
                $membership->removeRight($right);
            }
        }

        foreach ($normalized as $domainValue => $level) {
            $domain = RightDomain::from($domainValue);
            $existing = $membership->rightFor($domain);
            if (null !== $existing) {
                $existing->setLevel($level);
            } else {
                $membership->addRight(new Right($domain, $level));
            }
        }
    }
}
