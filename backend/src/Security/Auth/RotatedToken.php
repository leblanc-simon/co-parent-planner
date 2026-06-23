<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Entity\User;

/** Résultat d'une rotation de refresh token : l'utilisateur et le nouveau jeton clair. */
final readonly class RotatedToken
{
    public function __construct(
        public User $user,
        public string $plaintext,
    ) {
    }
}
