<?php

declare(strict_types=1);

namespace App\Security\Auth;

/**
 * Politique de mot de passe (Lot 0). Longueur privilégiée à la complexité (orientation NIST) ;
 * borne haute pour éviter le DoS Argon2id (§4.5).
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 12;
    public const int MAX_LENGTH = 128;
}
