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
use App\Repository\MembershipRepository;
use App\Repository\NotificationDispatchRepository;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Service\Family\Household;
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
        private readonly MembershipRepository $memberships,
        private readonly Household $household,
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
        $email = $this->compose($message, $type, $user->getEmail(), $locale, $tz);

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

    private function compose(SendNotification $message, NotificationType $type, string $to, string $locale, \DateTimeZone $tz): TemplatedEmail
    {
        $title = $message->payloadRefs['title'] ?? '';
        $subject = $this->translator->trans('email.notif.'.$type->value.'.subject', ['title' => $title], 'messages', $locale);

        [$bodyKey, $bodyParams] = $this->body($type, $message->payloadRefs, $locale, $tz);
        $body = $this->translator->trans($bodyKey, $bodyParams + ['title' => $title], 'messages', $locale);

        return (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'CoParentPlanner'))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate('emails/notification.html.twig')
            // Partie texte explicite : le lien d'action doit rester lisible en texte seul (C18).
            ->textTemplate('emails/notification.txt.twig')
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
     * Choisit la variante du corps et ses paramètres.
     *
     * **Nommage du foyer (E3)** : quand la notification de garde porte sur un jour identifié, le
     * corps dit « Le {date} est désormais chez {household} » — « Sophie », ou « Sophie et Bob » si
     * un beau-parent lui est associé. Le nom est résolu ICI, depuis l'identifiant transporté : le
     * payload d'un message Messenger vit en clair en base, et y recopier un `full_name` 🔒 le
     * ferait fuiter dans un dump SQL brut, ce que le §7 interdit.
     *
     * Toute ref manquante (membre retiré depuis, période de plusieurs jours) fait retomber sur le
     * corps générique : un e-mail moins précis vaut mieux qu'une phrase à trous.
     *
     * @param array<string, string> $refs
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function body(NotificationType $type, array $refs, string $locale, \DateTimeZone $tz): array
    {
        $key = 'email.notif.'.$type->value.'.body';
        if (NotificationType::CustodyChanged !== $type
            || !isset($refs['date'], $refs['membershipId'])
            || !Uuid::isValid($refs['membershipId'])) {
            return [$key, []];
        }

        $parent = $this->memberships->find(Uuid::fromString($refs['membershipId']));
        $household = $this->household->name($parent, $locale);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $refs['date'], $tz);
        if ('' === $household || !$date instanceof \DateTimeImmutable) {
            return [$key, []];
        }

        return [$key.'_day', ['date' => $this->longDate($date, $locale, $tz), 'household' => $household]];
    }

    /**
     * Date en toutes lettres dans la langue du destinataire (« 12 septembre 2026 »).
     *
     * Formatée par ICU plutôt que par un format PHP écrit à la main : l'ordre des éléments et le
     * nom du mois changent avec la locale, et l'e-mail est le seul écran que l'application ne
     * relit pas avant envoi.
     */
    private function longDate(\DateTimeImmutable $date, string $locale, \DateTimeZone $tz): string
    {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            $tz,
        );

        return $formatter->format($date) ?: $date->format('Y-m-d');
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
            NotificationCategory::Expenses => '/depenses',
            default => '/',
        };
    }
}
