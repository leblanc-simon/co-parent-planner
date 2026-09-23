<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Canaux de notification (cdc §2.9). `email` depuis le Lot 7 ; `push` (mobile) depuis le Lot 11.
 */
enum NotificationChannel: string
{
    case Email = 'email';
    case Push = 'push';
}
