<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/** Jeton de vérification/reset invalide, expiré ou déjà consommé (§3.1 sc.6, §3.4). */
final class InvalidOneTimeTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(400, 'auth.error.invalid_token');
    }

    public function problemType(): string
    {
        return '/errors/invalid-token';
    }
}
