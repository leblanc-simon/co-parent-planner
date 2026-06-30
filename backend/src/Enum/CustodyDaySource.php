<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Origine d'un jour de garde matérialisé (cdc §2.4). Priorité : exception > holiday > base.
 */
enum CustodyDaySource: string
{
    case Base = 'base';
    case Holiday = 'holiday';
    case Exception = 'exception';

    /** Rang de priorité (plus grand = prioritaire) — un upsert ne downgrade jamais une source. */
    public function priority(): int
    {
        return match ($this) {
            self::Base => 0,
            self::Holiday => 1,
            self::Exception => 2,
        };
    }
}
