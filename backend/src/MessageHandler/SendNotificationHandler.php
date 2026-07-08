<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\NotificationDispatch;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\NotificationCategory;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Message\SendNotification;
use App\Repository\FamilyRepository;
use App\Repository\NotificationDispatchRepository;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Mime\Address;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Worker d'envoi d'une notification e-mail (Lot 7 §3.1). Idempotent sur `notificationKey` (table
 * `NotificationDispatch`) ; applique les quiet-hours (report via `DelayStamp`) ; rend le template
 * localisé (fuseau famille) ; journalise l'envoi **sans** contenu 🔒 en clair. Minimisation : l'e-mail
 * ne contient qu'un titre non sensible + un lien vers l'app (jamais le texte d'un message).
 */
#[AsMessageHandler]
final class SendNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly FamilyRepository $families,
        private readonly NotificationDispatchRepository $dispatches,
        private readonly \App\Service\Notification\QuietHours $quietHours,
        private readonly \App\Service\Notification\LocaleResolver $localeResolver,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly ClockInterface $clock,
        #[Autowire('%env(APP_WEB_BASE_URL)%')]
        private readonly string $webBaseUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {
    }

    public function __invoke(SendNotification $message): void
    {
        $user = $this->users->find(Uuid::fromString($message->recipientUserId));
        if (!$user instanceof User) {
            return; // destinataire supprimé → rien à envoyer (ACK)
        }
        $type = NotificationType::from($message->type);

        $dispatch = $this->dispatches->findOneByKey($message->notificationKey);
        if (null !== $dispatch && NotificationStatus::Sent === $dispatch->getStatus()) {
            return; // idempotence : déjà envoyée
        }
        if (null === $dispatch) {
            $dispatch = new NotificationDispatch($message->notificationKey, $user, $type);
            $this->em->persist($dispatch);
        }

        $family = null !== $message->familyId ? $this->families->find(Uuid::fromString($message->familyId)) : null;
        $tz = new \DateTimeZone($family?->getTimezone() ?? 'Europe/Paris');
        $now = $this->clock->now();

        // Quiet-hours : report une seule fois (le message déjà 'deferred' ne se re-reporte pas).
        if (NotificationStatus::Deferred !== $dispatch->getStatus()) {
            $target = $this->quietHours->deferUntil($user, $now, $tz);
            if (null !== $target) {
                $dispatch->setStatus(NotificationStatus::Deferred);
                $this->em->flush();
                $delayMs = max(0, ($target->getTimestamp() - $now->getTimestamp()) * 1000);
                $this->bus->dispatch($message, [new DelayStamp($delayMs)]);

                return;
            }
        }

        $locale = $this->localeResolver->resolve($user, $family);
        $email = $this->compose($message, $type, $user->getEmail(), $locale);

        $dispatch->setStatus(NotificationStatus::Sending);
        $this->em->flush();

        $this->mailer->send($email); // peut lever → retry Messenger

        $dispatch->setStatus(NotificationStatus::Sent)->setSentAt($now);
        $this->audit->persist(
            AuditAction::NotificationSent,
            $user,
            $family,
            metadata: ['category' => $type->category()->value, 'type' => $type->value],
        );
        $this->em->flush();
    }

    private function compose(SendNotification $message, NotificationType $type, string $to, string $locale): TemplatedEmail
    {
        $title = $message->payloadRefs['title'] ?? '';
        $subject = $this->translator->trans('email.notif.'.$type->value.'.subject', ['title' => $title], 'messages', $locale);
        $body = $this->translator->trans('email.notif.'.$type->value.'.body', ['title' => $title], 'messages', $locale);

        return (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'CoParentPlanner'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/notification.html.twig')
            ->locale($locale)
            ->context([
                'locale' => $locale,
                'heading' => $subject,
                'body' => $body,
                'actionUrl' => $this->link($type, $message->payloadRefs),
                'actionLabel' => $this->translator->trans('email.notif.cta', [], 'messages', $locale),
            ]);
    }

    /**
     * @param array<string, string> $refs
     */
    private function link(NotificationType $type, array $refs): string
    {
        $base = rtrim($this->webBaseUrl, '/');

        return $base.match ($type->category()) {
            NotificationCategory::Exchanges => '/echanges'.(isset($refs['exchangeId']) ? '?exchange='.rawurlencode($refs['exchangeId']) : ''),
            NotificationCategory::Agenda => '/agenda',
            NotificationCategory::Custody => '/garde',
            default => '/',
        };
    }
}
