<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendInvitationEmail;
use App\Security\Encryption\FieldEncryptor;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Construit et envoie l'e-mail d'invitation localisé (§3.3.B). Lien à usage unique vers
 * l'acceptation. Exécuté en asynchrone par un worker Messenger ; aucun token loggué en clair.
 */
#[AsMessageHandler]
final class SendInvitationEmailHandler
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly FieldEncryptor $encryptor,
        #[Autowire('%env(APP_WEB_BASE_URL)%')]
        private readonly string $webBaseUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress,
    ) {
    }

    public function __invoke(SendInvitationEmail $message): void
    {
        $token = $this->encryptor->decrypt($message->token);
        $link = rtrim($this->webBaseUrl, '/').'/invitations/accept?token='.rawurlencode((string) $token);

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'CoParentPlanner'))
            ->to($message->toEmail)
            ->subject($this->translator->trans('email.invitation.subject', ['family' => $message->familyName], 'messages', $message->locale))
            ->htmlTemplate('emails/invitation.html.twig')
            // Partie texte explicite : le lien d'acceptation doit rester lisible en texte seul (C18).
            ->textTemplate('emails/invitation.txt.twig')
            ->locale($message->locale)
            ->context([
                'link' => $link,
                'locale' => $message->locale,
                'familyName' => $message->familyName,
            ]);

        $this->mailer->send($email);
    }
}
