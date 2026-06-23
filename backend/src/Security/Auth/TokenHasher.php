<?php

declare(strict_types=1);

namespace App\Security\Auth;

/**
 * Génère des jetons opaques forts et leur empreinte SHA-256.
 *
 * Les jetons (refresh, vérification e-mail, reset) sont remis au client en clair mais
 * **stockés hachés** en base (invariant §4.8 : jamais de jeton en clair en base).
 */
final class TokenHasher
{
    /** Jeton opaque url-safe (256 bits d'entropie). */
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
