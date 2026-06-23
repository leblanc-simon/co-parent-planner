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
}
