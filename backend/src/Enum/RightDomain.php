<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Domaines de droits (cdc §2.2). Un Voter par domaine.
 */
enum RightDomain: string
{
    case Custody = 'custody';
    case Agenda = 'agenda';
    case ChildrenNotes = 'children_notes';
    case Expenses = 'expenses';
    case Exchanges = 'exchanges';
    case FamilySettings = 'family_settings';

    /**
     * Domaine de pure structure familiale : édition réservée à un admin, jamais à un enfant.
     */
    public function isStructural(): bool
    {
        return self::FamilySettings === $this;
    }

    /** L'édition de ce domaine exige-t-elle le flag admin ? (family_settings) */
    public function requiresAdminToEdit(): bool
    {
        return self::FamilySettings === $this;
    }

    /** Domaine interdit (forcé `none`) aux enfants (cdc §2.2 : expenses forcé none). */
    public function isForbiddenForChild(): bool
    {
        return self::Expenses === $this;
    }

    /** Domaine v2 (Dépenses), inerte tant que le module n'est pas activé (cdc §1.3). */
    public function isV2(): bool
    {
        return self::Expenses === $this;
    }
}
