<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Finalité d'un jeton à usage unique (entité unifiée OneTimeToken — Lot 0 §5.1).
 */
enum OneTimeTokenPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
    // Auth renforcée admin technique (Lot 8, cdc §6.2) : grant step-up court, validé sans
    // consommation (réutilisable dans sa fenêtre de fraîcheur) sur les routes /api/admin/rgpd/*.
    case AdminStepUp = 'admin_step_up';
}
