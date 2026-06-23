<?php

declare(strict_types=1);

namespace App\Security\Auth\Exception;

/**
 * Réutilisation d'un refresh token déjà tourné (détection de vol/rejeu) → invalidation de toute
 * la famille de tokens (§3.3, décision D3).
 */
final class RefreshReuseException extends AuthException
{
    public function __construct()
    {
        parent::__construct(401, 'auth.error.refresh_reuse');
    }

    public function problemType(): string
    {
        return '/errors/refresh-reuse';
    }
}
