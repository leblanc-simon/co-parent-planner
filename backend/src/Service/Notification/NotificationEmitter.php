<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\NotificationType;
use App\Message\SendNotification;
use App\Repository\NotificationPrefRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Routeur de notifications (Lot 7 §3.1) : à appeler **après commit** d'une mutation métier. Résout les
 * destinataires (Voter), exclut l'auteur (pas d'auto-notification), filtre par préférence e-mail, puis
 * dispatch un message Messenger idempotent par destinataire. Best-effort : n'interrompt pas la requête.
 */
final class NotificationEmitter
{
    public function __construct(
        private readonly RecipientResolver $recipients,
        private readonly NotificationPrefRepository $prefs,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, string> $payloadRefs identifiants/libellés non sensibles pour le rendu
     */
    public function emit(
        NotificationType $type,
        Family $family,
        ?Membership $author,
        string $factId,
        array $payloadRefs = [],
    ): void {
        $authorId = null !== $author ? (string) $author->getId() : null;

        // Best-effort (§4.6) : un échec de résolution/dispatch de notification ne doit JAMAIS annuler
        // la mutation métier déjà committée. En prod l'envoi est asynchrone ; on journalise et on continue.
        try {
            foreach ($this->recipients->resolve($family, $type) as $membership) {
                if (null !== $authorId && (string) $membership->getId() === $authorId) {
                    continue; // pas d'auto-notification
                }
                $user = $membership->getUser();
                if (!$this->prefs->isEmailEnabled($user, $type->category())) {
                    continue;
                }
                $key = $type->value.':'.$factId.':'.$user->getId();
                $this->bus->dispatch(new SendNotification(
                    $key,
                    (string) $user->getId(),
                    (string) $family->getId(),
                    $type->value,
                    $payloadRefs,
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Échec d\'émission de notification.', ['type' => $type->value, 'exception' => $e]);
        }
    }
}
