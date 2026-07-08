<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Ordre d'envoi d'une notification e-mail à **un** destinataire (Lot 7 §3.1). Traité en asynchrone,
 * idempotent sur `notificationKey`. `payloadRefs` ne contient **que** des identifiants et libellés
 * non sensibles (jamais de champ 🔒 en clair) ; le handler recharge/déchiffre en mémoire au besoin.
 */
final class SendNotification
{
    /**
     * @param array<string, string> $payloadRefs
     */
    public function __construct(
        public readonly string $notificationKey,
        public readonly string $recipientUserId,
        public readonly ?string $familyId,
        public readonly string $type,
        public readonly array $payloadRefs = [],
    ) {
    }
}
