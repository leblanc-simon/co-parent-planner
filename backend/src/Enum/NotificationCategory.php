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

    /** `expenses` est réservé v2 (module dépenses masqué) : inactif en v1. */
    public function isV1(): bool
    {
        return self::Expenses !== $this;
    }

    /** @return list<self> catégories actives en v1 (hors `expenses`). */
    public static function v1Cases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $c): bool => $c->isV1()));
    }
}
