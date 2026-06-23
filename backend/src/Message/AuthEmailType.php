<?php

declare(strict_types=1);

namespace App\Message;

/** Type d'e-mail transactionnel d'authentification (localisé). */
enum AuthEmailType: string
{
    case Verification = 'verification';
    case PasswordReset = 'password_reset';
    case PasswordChanged = 'password_changed';
}
