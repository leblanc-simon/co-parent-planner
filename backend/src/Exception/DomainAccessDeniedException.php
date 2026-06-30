<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\RightDomain;
use App\Enum\RightLevel;

/**
 * Autorisation refusée par un Voter de domaine (§3.1, §6.3). Le `detail` précise le droit requis,
 * ex. « Droit 'family_settings:edit' requis. ».
 */
final class DomainAccessDeniedException extends AbstractProblemException
{
    public const string TYPE = '/errors/forbidden';

    public function __construct(RightDomain $domain, RightLevel $level)
    {
        parent::__construct(403, self::TYPE, 'family.error.right_required', [
            'right' => $domain->value.':'.$level->value,
        ]);
    }
}
