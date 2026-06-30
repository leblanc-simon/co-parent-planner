<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Demande d'envoi asynchrone (Messenger) de l'e-mail d'invitation localisé (§3.3.B).
 *
 * Le `token` est **chiffré en transit** (AES-256-GCM) par l'émetteur et déchiffré par le handler :
 * il ne transite jamais en clair dans la file Messenger, ni n'est persisté en base.
 */
final readonly class SendInvitationEmail
{
    public function __construct(
        public string $toEmail,
        public string $locale,
        public string $familyName,
        public string $role,
        public string $token,
    ) {
    }
}
