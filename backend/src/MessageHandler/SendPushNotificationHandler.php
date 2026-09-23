<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\DeviceToken;
use App\Entity\Family;
use App\Entity\NotificationDispatch;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\NotificationChannel;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Enum\UserStatus;
use App\Message\SendPushNotification;
use App\Repository\DeviceTokenRepository;
use App\Repository\FamilyRepository;
use App\Repository\NotificationDispatchRepository;
use App\Repository\NotificationPrefRepository;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Service\Notification\LocaleResolver;
use App\Service\Notification\Push\PushComposer;
use App\Service\Notification\Push\PushDeliveryException;
use App\Service\Notification\Push\PushResult;
use App\Service\Notification\Push\PushSender;
use App\Service\Notification\QuietHours;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Worker d'envoi push (Lot 11 §3.3–3.6) : un message par destinataire, fan-out sur ses appareils
 * actifs, chacun traité indépendamment.
 *
 * - **Quiet-hours** : même politique que l'e-mail (Lot 7) — report à la fin de la plage, une seule fois.
 * - **Opt-in relu à l'envoi** : un push reporté au matin ne part pas si l'utilisateur a coupé la
 *   catégorie entre-temps.
 * - **Idempotence par appareil** : clé `push:<événement>:<appareil>` dans `NotificationDispatch`. Un
 *   rejeu (échec passager d'un autre appareil, worker redémarré) ne re-sert pas un appareil servi.
 * - **Nettoyage** : seul un refus définitif du jeton l'invalide ; une erreur passagère lève en fin de
 *   boucle pour que Messenger rejoue, après avoir servi les autres appareils.
 */
#[AsMessageHandler]
final class SendPushNotificationHandler
{
    /** Longueur de `notification_dispatches.notification_key`. */
    private const int KEY_MAX = 128;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly FamilyRepository $families,
        private readonly DeviceTokenRepository $devices,
        private readonly NotificationPrefRepository $prefs,
        private readonly NotificationDispatchRepository $dispatches,
        private readonly QuietHours $quietHours,
        private readonly LocaleResolver $localeResolver,
        private readonly PushComposer $composer,
        private readonly PushSender $sender,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPushNotification $message): void
    {
        $user = $this->users->find(Uuid::fromString($message->recipientUserId));
        if (!$user instanceof User || UserStatus::Deleted === $user->getStatus() || !$this->sender->isEnabled()) {
            return;
        }
        $type = NotificationType::from($message->type);
        if (!$this->prefs->isPushEnabled($user, $type->category())) {
            return;
        }

        $family = null !== $message->familyId ? $this->families->find(Uuid::fromString($message->familyId)) : null;
        $now = $this->clock->now();

        if (!$message->deferred) {
            $tz = new \DateTimeZone($family?->getTimezone() ?? 'Europe/Paris');
            $target = $this->quietHours->deferUntil($user, $now, $tz);
            if (null !== $target) {
                $delayMs = max(0, ($target->getTimestamp() - $now->getTimestamp()) * 1000);
                $this->bus->dispatch($message->asDeferred(), [new DelayStamp($delayMs)]);

                return;
            }
        }

        $delivered = 0;
        $retry = false;
        foreach ($this->devices->findActiveForUser($user) as $device) {
            $result = $this->deliver($message, $type, $user, $family, $device, $now);
            if (PushResult::Delivered === $result) {
                ++$delivered;
            } elseif (PushResult::Retryable === $result || PushResult::AuthFailure === $result) {
                $retry = true;
            }
        }

        if ($delivered > 0) {
            $this->audit->log(
                AuditAction::NotificationSent,
                $user,
                $family,
                metadata: ['category' => $type->category()->value, 'type' => $type->value, 'channel' => 'push', 'devices' => $delivered],
            );
        }
        if ($retry) {
            throw new PushDeliveryException(\sprintf('Push %s : au moins un appareil à resservir.', $message->eventKey));
        }
    }

    /** Envoi à un appareil ; `null` si l'appareil a déjà été servi pour cet événement. */
    private function deliver(
        SendPushNotification $message,
        NotificationType $type,
        User $user,
        ?Family $family,
        DeviceToken $device,
        \DateTimeImmutable $now,
    ): ?PushResult {
        $key = $this->key($message->eventKey, $device);
        $dispatch = $this->dispatches->findOneByKey($key);
        if (null !== $dispatch && NotificationStatus::Sent === $dispatch->getStatus()) {
            return null;
        }
        if (null === $dispatch) {
            $dispatch = new NotificationDispatch($key, $user, $type, NotificationChannel::Push);
            $this->em->persist($dispatch);
        }
        $dispatch->setStatus(NotificationStatus::Sending);
        $this->em->flush();

        $locale = $this->localeResolver->resolve($user, $family, $device);
        $content = $this->composer->compose($type, $message->eventKey, $message->familyId, $message->payloadRefs, $locale);
        $result = $this->sender->send($device, $content);

        match ($result) {
            PushResult::Delivered => $this->succeeded($dispatch, $device, $now),
            PushResult::InvalidToken => $this->invalidated($dispatch, $device, $now),
            PushResult::Rejected => $dispatch->setStatus(NotificationStatus::Failed),
            // Remis en attente : le rejeu de Messenger le reprendra.
            PushResult::Retryable, PushResult::AuthFailure => $dispatch->setStatus(NotificationStatus::Pending),
        };
        $this->em->flush();

        if (PushResult::Retryable === $result || PushResult::AuthFailure === $result) {
            $this->logger->warning('Push non remis, nouvel essai prévu.', [
                'event' => $message->eventKey,
                'device' => (string) $device->getId(),
                'result' => $result->name,
            ]);
        }

        return $result;
    }

    private function succeeded(NotificationDispatch $dispatch, DeviceToken $device, \DateTimeImmutable $now): void
    {
        $dispatch->setStatus(NotificationStatus::Sent)->setSentAt($now);
        $device->delivered($now);
    }

    private function invalidated(NotificationDispatch $dispatch, DeviceToken $device, \DateTimeImmutable $now): void
    {
        $dispatch->setStatus(NotificationStatus::Failed);
        $device->invalidate($now);
        $this->audit->persist(
            AuditAction::DeviceInvalidated,
            $device->getUser(),
            metadata: ['platform' => $device->getPlatform()->value],
            entityType: 'DeviceToken',
            entityId: (string) $device->getId(),
        );
    }

    /** Clé d'idempotence par appareil, ramenée à une empreinte si le fait est trop long. */
    private function key(string $eventKey, DeviceToken $device): string
    {
        $key = 'push:'.$eventKey.':'.$device->getId();

        return \strlen($key) <= self::KEY_MAX ? $key : 'push:'.hash('sha256', $eventKey).':'.$device->getId();
    }
}
