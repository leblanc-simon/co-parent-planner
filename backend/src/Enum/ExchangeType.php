<?php

declare(strict_types=1);

namespace App\Enum;

/** Type d'échange (cdc §2.6, Lot 6) : fil ouvert (`discussion`) ou choix à trancher (`decision`). */
enum ExchangeType: string
{
    case Discussion = 'discussion';
    case Decision = 'decision';
}
