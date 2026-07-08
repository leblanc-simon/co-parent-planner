<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'un envoi de notification (Lot 7 §3.7). */
enum NotificationStatus: string
{
    case Pending = 'pending';
    case Deferred = 'deferred';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
