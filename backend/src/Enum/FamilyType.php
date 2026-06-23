<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de famille (cdc §2.1).
 *
 * - separated : famille séparée → planning de garde actif.
 * - intact    : famille unie → pas de garde (onglet masqué).
 */
enum FamilyType: string
{
    case Separated = 'separated';
    case Intact = 'intact';

    public function hasCustody(): bool
    {
        return self::Separated === $this;
    }
}
