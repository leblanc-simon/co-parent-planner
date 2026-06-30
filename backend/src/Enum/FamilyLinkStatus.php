<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un lien entre deux familles (cdc §2.6, Lot 1 §3.6).
 *
 * - pending : proposé par une famille, en attente d'acceptation par l'autre.
 * - active  : accepté par la famille destinataire ; exploitable par l'overlay (Lot 5).
 *
 * La déliaison supprime l'enregistrement (pas d'état « supprimé » persistant).
 */
enum FamilyLinkStatus: string
{
    case Pending = 'pending';
    case Active = 'active';

    public function isActive(): bool
    {
        return self::Active === $this;
    }
}
