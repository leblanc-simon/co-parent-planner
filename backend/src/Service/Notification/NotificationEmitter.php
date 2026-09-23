<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\NotificationType;
use App\Enum\RightLevel;
use App\Message\SendNotification;
use App\Message\SendPushNotification;
use App\Repository\DeviceTokenRepository;
use App\Repository\NotificationPrefRepository;
use App\Service\Notification\Push\PushSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Routeur de notifications (Lot 7 §3.1) : à appeler **après commit** d'une mutation métier. Résout les
 * destinataires (Voter), exclut l'auteur (pas d'auto-notification), puis fan-out **par canal** : un
 * message e-mail si la préférence e-mail le permet, un message push (Lot 11) si la préférence push
 * est activée **et** que le destinataire a au moins un appareil actif — pas d'appareil, pas de
 * permission système, rien à enfiler. Les deux canaux sont indépendants. Best-effort : n'interrompt
 * pas la requête.
 */
final class NotificationEmitter
{
    public function __construct(
        private readonly RecipientResolver $recipients,
        private readonly NotificationPrefRepository $prefs,
        private readonly DeviceTokenRepository $devices,
        private readonly PushSender $pushSender,
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
                $this->dispatch($type, $family, $membership, $factId, $payloadRefs);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Échec d\'émission de notification.', ['type' => $type->value, 'exception' => $e]);
        }
    }

    /**
     * Notification adressée à **un** membre désigné, et non à tous les habilités du domaine.
     *
     * Le rappel de dépense (Lot 10 US-DEP-2) en a besoin : il s'adresse au parent débiteur, pas à
     * quiconque peut voir les dépenses. Le droit du destinataire reste vérifié — un membre qui a
     * perdu l'accès au domaine ne doit pas continuer à en recevoir les e-mails.
     *
     * @param array<string, string> $payloadRefs
     */
    public function emitTo(
        NotificationType $type,
        Family $family,
        Membership $recipient,
        string $factId,
        array $payloadRefs = [],
    ): void {
        try {
            if (!$recipient->can($type->rightDomain(), RightLevel::View)) {
                return;
            }
            $this->dispatch($type, $family, $recipient, $factId, $payloadRefs);
        } catch (\Throwable $e) {
            $this->logger->warning('Échec d\'émission de notification ciblée.', ['type' => $type->value, 'exception' => $e]);
        }
    }

    /**
     * @param array<string, string> $payloadRefs
     */
    private function dispatch(
        NotificationType $type,
        Family $family,
        Membership $membership,
        string $factId,
        array $payloadRefs,
    ): void {
        $user = $membership->getUser();
        $eventKey = $type->value.':'.$factId;

        if ($this->prefs->isEmailEnabled($user, $type->category())) {
            $this->bus->dispatch(new SendNotification(
                $eventKey.':'.$user->getId(),
                (string) $user->getId(),
                (string) $family->getId(),
                $type->value,
                $payloadRefs,
            ));
        }

        if ($this->pushSender->isEnabled()
            && $this->prefs->isPushEnabled($user, $type->category())
            && $this->devices->hasActiveForUser($user)) {
            $this->bus->dispatch(new SendPushNotification(
                $eventKey,
                (string) $user->getId(),
                (string) $family->getId(),
                $type->value,
                $payloadRefs,
            ));
        }
    }
}
