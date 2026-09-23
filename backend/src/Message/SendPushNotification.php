<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Ordre d'envoi push à **un** destinataire, sur tous ses appareils actifs (Lot 11 §3.3). Pendant du
 * {@see SendNotification} e-mail : même clé d'événement, mêmes `payloadRefs` non sensibles.
 *
 * `eventKey` identifie l'événement (`<type>:<fait>`), indépendamment du canal et du destinataire :
 * il sert de corrélation côté app (Mercure déjà reçu ⇒ pas de seconde bannière) et de base à la clé
 * d'idempotence par appareil. `deferred` marque un message déjà reporté par les quiet-hours.
 */
final class SendPushNotification
{
    /**
     * @param array<string, string> $payloadRefs
     */
    public function __construct(
        public readonly string $eventKey,
        public readonly string $recipientUserId,
        public readonly ?string $familyId,
        public readonly string $type,
        public readonly array $payloadRefs = [],
        public readonly bool $deferred = false,
    ) {
    }

    public function asDeferred(): self
    {
        return new self($this->eventKey, $this->recipientUserId, $this->familyId, $this->type, $this->payloadRefs, true);
    }
}
