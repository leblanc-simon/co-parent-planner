<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un membership (cdc §5.2).
 *
 * - active  : membre effectif (compte pour le scope X-Family-Id).
 * - invited : invitation acceptée en attente / placeholder (gestion fine au Lot 1).
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
}
