<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Modèle de rythme de garde (cdc §2.4, Lot 3 §5.1).
 *
 * - alternating      : une semaine sur deux.
 * - pattern_2_2_3    : cycle de 14 jours (2/2/3 en rotation), motif canonique figé.
 * - midweek_weekends : jours fixes en semaine + week-ends alternés (config dans `settings`).
 * - custom           : 14 créneaux nuit par nuit (config dans `custom_pattern`).
 */
enum CustodyPatternKind: string
{
    case Alternating = 'alternating';
    case Pattern2223 = 'pattern_2_2_3';
    case MidweekWeekends = 'midweek_weekends';
    case Custom = 'custom';

    public function requiresCustomPattern(): bool
    {
        return self::Custom === $this;
    }

    public function requiresSettings(): bool
    {
        return self::MidweekWeekends === $this;
    }
}
