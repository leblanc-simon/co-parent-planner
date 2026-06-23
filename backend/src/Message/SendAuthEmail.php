<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Demande d'envoi asynchrone (Messenger) d'un e-mail d'auth localisé (§3.1, §4.6).
 * L'inscription/forgot ne bloque pas sur l'envoi : ce message est traité par un worker.
 *
 * Le `token` est **chiffré en transit** (AES-256-GCM) par l'émetteur et déchiffré par le handler :
 * il ne transite donc jamais en clair dans la file Messenger (cf. transport Doctrine/Redis).
 */
final readonly class SendAuthEmail
{
    public function __construct(
        public string $toEmail,
        public string $locale,
        public AuthEmailType $type,
        public ?string $token = null,
    ) {
    }
}
