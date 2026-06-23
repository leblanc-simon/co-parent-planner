<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'une invitation (cdc §5.2).
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    /** L'invitation peut-elle encore être acceptée ? */
    public function isAcceptable(): bool
    {
        return self::Pending === $this;
    }
}
