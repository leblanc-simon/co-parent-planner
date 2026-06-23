<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/** Connexion refusée : e-mail non vérifié (décision D1 — aucun token avant vérification). */
final class EmailNotVerifiedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(403, 'auth.error.email_not_verified');
    }

    public function problemType(): string
    {
        return '/errors/email-not-verified';
    }
}
