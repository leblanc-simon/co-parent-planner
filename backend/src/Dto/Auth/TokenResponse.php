<?php

declare(strict_types=1);

namespace App\Dto\Auth;

/**
 * Jetons délivrés à la connexion / au rafraîchissement.
 *
 * Mobile : consommés depuis le corps (Bearer). Web : également posés en cookies HttpOnly
 * (décision D2) par le subscriber ; le JS ne lit pas ces valeurs.
 */
final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $tokenType = 'Bearer',
    ) {
    }
}
