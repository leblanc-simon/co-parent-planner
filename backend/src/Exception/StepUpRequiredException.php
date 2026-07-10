<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Auth renforcée requise pour l'administration technique RGPD (cdc §6.2) : ROLE_ADMIN ne suffit pas
 * sans un grant step-up frais et valide (en-tête `X-Admin-Step-Up`).
 */
final class StepUpRequiredException extends AbstractProblemException
{
    public const string TYPE = '/errors/step-up-required';

    public function __construct()
    {
        parent::__construct(403, self::TYPE, 'admin.error.step_up_required');
    }
}
