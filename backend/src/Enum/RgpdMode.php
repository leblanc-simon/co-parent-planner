<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode d'exécution d'une opération RGPD (cdc §5.2 / §7.1) : réel ou simulation.
 */
enum RgpdMode: string
{
    case Live = 'live';
    case DryRun = 'dry_run';
}
