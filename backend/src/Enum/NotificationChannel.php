<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Canaux de notification (cdc §2.9). `push` réservé v2 (Lot 10), inactif en v1.
 */
enum NotificationChannel: string
{
    case Email = 'email';
    case Push = 'push';

    public function isV2(): bool
    {
        return self::Push === $this;
    }
}
