<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rôle d'un membre dans une famille (cdc §2.2).
 *
 * - parent      : tous les droits (forcés `edit` partout, non éditables).
 * - step_parent : droits granulaires (none|view|edit par domaine).
 * - child       : droits limités ; jamais `expenses`, jamais d'édition de structure.
 */
enum MembershipRole: string
{
    case Parent = 'parent';
    case StepParent = 'step_parent';
    case Child = 'child';

    /** Le rôle force-t-il `edit` sur tous les domaines ? */
    public function forcesEdit(): bool
    {
        return self::Parent === $this;
    }

    public function isChild(): bool
    {
        return self::Child === $this;
    }
}
