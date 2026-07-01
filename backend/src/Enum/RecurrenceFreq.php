<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Fréquence de récurrence d'une série d'événements (cdc §2.5 ; Lot 4 §3.2). `none` = événement
 * ponctuel. Le pas de `weekly`/`biweekly` est un nombre de jours fixe ; `monthly` conserve le
 * quantième (clamp au dernier jour pour les mois courts — Lot 4 §3.2.1).
 */
enum RecurrenceFreq: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function isRecurring(): bool
    {
        return self::None !== $this;
    }

    /** Pas en jours pour les fréquences à cadence fixe ; null pour `none`/`monthly` (calcul par mois). */
    public function stepDays(): ?int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Biweekly => 14,
            self::None, self::Monthly => null,
        };
    }
}
