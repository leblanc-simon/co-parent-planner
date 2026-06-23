<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type d'opération RGPD (cdc §5.2). Table posée au Lot 0, alimentée au Lot 8.
 */
enum RgpdOperationType: string
{
    case Export = 'export';
    case Erasure = 'erasure';
    case Purge = 'purge';
    case Anonymize = 'anonymize';
    case Report = 'report';
}
