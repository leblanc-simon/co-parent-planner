<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\Security\Encryption\FieldEncryptor;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Construit et envoie l'e-mail d'auth localisé (templates Twig + catalogue ICU).
 * Exécuté en asynchrone par un worker Messenger.
 */
#[AsMessageHandler]
final class SendAuthEmailHandler
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

    public function __invoke(SendAuthEmail $message): void
    {
        [$template, $subjectKey, $path] = match ($message->type) {
            AuthEmailType::Verification => ['emails/verification.html.twig', 'email.verification.subject', '/verify-email'],
            AuthEmailType::PasswordReset => ['emails/reset.html.twig', 'email.reset.subject', '/reset-password'],
            AuthEmailType::PasswordChanged => ['emails/password_changed.html.twig', 'email.password_changed.subject', null],
        };

        $link = null;
        $token = $this->encryptor->decrypt($message->token); // déchiffré (chiffré en transit)
        if (null !== $path && null !== $token) {
            $link = rtrim($this->webBaseUrl, '/').$path.'?token='.rawurlencode($token);
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'CoParentPlanner'))
            ->to($message->toEmail)
            ->subject($this->translator->trans($subjectKey, [], 'messages', $message->locale))
            ->htmlTemplate($template)
            ->locale($message->locale)
            ->context([
                'link' => $link,
                'locale' => $message->locale,
            ]);

        $this->mailer->send($email);
    }
}
