<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégories de notification (cdc §2.9, §5.2). `expenses` réservé v2.
 */
enum NotificationCategory: string
{
    case Custody = 'custody';
    case Agenda = 'agenda';
    case Expenses = 'expenses';
    case Exchanges = 'exchanges';
    case Invitation = 'invitation';
}
