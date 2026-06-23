<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/** Identifiants invalides — message générique non énumérant (§3.2 sc.2). */
final class InvalidCredentialsException extends AuthException
{
    public function __construct()
    {
        parent::__construct(401, 'auth.error.invalid_credentials');
    }

    public function problemType(): string
    {
        return '/errors/invalid-credentials';
    }
}
