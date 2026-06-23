<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'un compte utilisateur (cdc §5.2).
 *
 * - pending   : inscrit, e-mail non vérifié → aucun accès complet, aucun token (décision D1).
 * - active    : e-mail vérifié → accès normal.
 * - suspended : désactivé administrativement.
 * - deleted   : anonymisé/supprimé (RGPD, Lot 8).
 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Deleted = 'deleted';

    /** Un compte peut-il obtenir des tokens d'accès ? (décision D1 §0.5) */
    public function canAuthenticate(): bool
    {
        return self::Active === $this;
    }
}
