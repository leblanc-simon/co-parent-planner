<?php

declare(strict_types=1);

namespace App\Enum;

/** Cycle de vie d'un échange (cdc §2.6, Lot 6) : `open` (en cours) → `decided` (tranché/clos). */
enum ExchangeStatus: string
{
    case Open = 'open';
    case Decided = 'decided';
}
