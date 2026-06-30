<?php

declare(strict_types=1);

namespace App\Enum;

/** Origine d'une période de vacances (cdc §2.4) : importée officiellement ou saisie manuellement. */
enum HolidaySource: string
{
    case Official = 'official';
    case Manual = 'manual';
}
