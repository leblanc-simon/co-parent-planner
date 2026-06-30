<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Genre d'une règle de garde appliquée à une période de vacances (cdc §2.4, Lot 3 §3.3/§5.1).
 */
enum HolidayRuleKind: string
{
    case AlternatingWeeks = 'alternating_weeks';
    case Split5050 = 'split_50_50';
    case FixedParent = 'fixed_parent';
    case EveAlternating = 'eve_alternating';
    case Custom = 'custom';
}
