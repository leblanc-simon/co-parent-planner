<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/** Trop de tentatives (rate-limit/lockout) — 429 avec Retry-After (§3.2 sc.4). */
final class TooManyAttemptsException extends AuthException
{
    public function __construct(int $retryAfterSeconds)
    {
        parent::__construct(429, 'auth.error.too_many_attempts', headers: ['Retry-After' => (string) max(0, $retryAfterSeconds)]);
    }

    public function problemType(): string
    {
        return '/errors/too-many-attempts';
    }
}
