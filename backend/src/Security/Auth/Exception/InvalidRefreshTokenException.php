<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/** Refresh token introuvable / expiré → re-login requis (§3.3). */
final class InvalidRefreshTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(401, 'auth.error.invalid_refresh_token');
    }

    public function problemType(): string
    {
        return '/errors/invalid-refresh-token';
    }
}
