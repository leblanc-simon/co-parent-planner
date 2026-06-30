<?php

declare(strict_types=1);

namespace App\Message;

/** Purge planifiée (§3.3.E) : marque `expired` les invitations `pending` échues. Idempotent. */
final readonly class PurgeExpiredInvitations
{
}
