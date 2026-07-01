<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Vue concernée par une préférence d'overlay famille liée (Lot 5 §5.2).
 */
enum OverlayView: string
{
    case Agenda = 'agenda';
    case Custody = 'custody';
}
