<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\MessageHandler\SendAuthEmailHandler;
use App\Security\Encryption\FieldEncryptor;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Couvre SendAuthEmailHandler::__invoke : construction du lien (token déchiffré + chemin
 * selon le type), envoi via le mailer, et cas « lien null » (PasswordChanged).
 * Sans kernel ni DB : mailer/translator mockés, FieldEncryptor réel (clé locale).
 */
final class SendAuthEmailHandlerTest extends TestCase
{
    private const WEB_BASE_URL = 'http://web.test';
    private const FROM_ADDRESS = 'no-reply@coparentplanner.test';

    private FieldEncryptor $encryptor;

    protected function setUp(): void
    {
        // Clé brute de 32 octets (le constructeur exige 256 bits).
        $this->encryptor = new FieldEncryptor(str_repeat('k', 32));
    }

    public function testVerificationEmailBuildsVerifyLink(): void
    {
        $captured = $this->dispatch(AuthEmailType::Verification, 'tok-clair');

        $context = $captured->getContext();
        self::assertSame(self::WEB_BASE_URL.'/verify-email?token=tok-clair', $context['link']);
        self::assertSame('fr-FR', $context['locale']);

        // Destinataire, expéditeur et sujet (issu du translator mocké).
        self::assertSame('verify@example.com', $captured->getTo()[0]->getAddress());
        self::assertSame(self::FROM_ADDRESS, $captured->getFrom()[0]->getAddress());
        self::assertSame('CoParentPlanner', $captured->getFrom()[0]->getName());
        self::assertSame('Sujet de test', $captured->getSubject());
    }

    public function testPasswordResetEmailBuildsResetLink(): void
    {
        $captured = $this->dispatch(AuthEmailType::PasswordReset, 'reset-token-42');

        self::assertSame(
            self::WEB_BASE_URL.'/reset-password?token=reset-token-42',
            $captured->getContext()['link'],
        );
    }

    public function testPasswordChangedEmailHasNullLink(): void
    {
        // PasswordChanged : pas de chemin → lien null, même si un token est fourni.
        $captured = $this->dispatch(AuthEmailType::PasswordChanged, 'ignored-token');

        self::assertNull($captured->getContext()['link']);
    }

    public function testTokenIsUrlEncodedInLink(): void
    {
        // Un token contenant des caractères réservés doit être rawurlencodé.
        $captured = $this->dispatch(AuthEmailType::Verification, 'a b/c+d');

        self::assertSame(
            self::WEB_BASE_URL.'/verify-email?token=a%20b%2Fc%2Bd',
            $captured->getContext()['link'],
        );
    }

    public function testNullTokenYieldsNullLink(): void
    {
        // Aucun token (le handler ne construit pas de lien).
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Sujet de test');

        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$captured): void {
                $captured = $email;
            });

        $handler = new SendAuthEmailHandler(
            $mailer,
            $translator,
            $this->encryptor,
            self::WEB_BASE_URL,
            self::FROM_ADDRESS,
        );

        $handler(new SendAuthEmail('x@example.com', 'fr-FR', AuthEmailType::Verification, null));

        self::assertInstanceOf(TemplatedEmail::class, $captured);
        self::assertNull($captured->getContext()['link']);
    }

    /**
     * Construit le handler avec des mocks, dispatch un SendAuthEmail et capture l'Email envoyé.
     */
    private function dispatch(AuthEmailType $type, string $plainToken): TemplatedEmail
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Sujet de test');

        $captured = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (Email $email) use (&$captured): void {
                $captured = $email;
            });

        $handler = new SendAuthEmailHandler(
            $mailer,
            $translator,
            $this->encryptor,
            self::WEB_BASE_URL,
            self::FROM_ADDRESS,
        );

        // Le token transite chiffré (AES-256-GCM) ; le handler le déchiffre.
        $message = new SendAuthEmail(
            $this->recipientFor($type),
            'fr-FR',
            $type,
            $this->encryptor->encrypt($plainToken),
        );

        $handler($message);

        self::assertInstanceOf(TemplatedEmail::class, $captured);

        return $captured;
    }

    private function recipientFor(AuthEmailType $type): string
    {
        return match ($type) {
            AuthEmailType::Verification => 'verify@example.com',
            AuthEmailType::PasswordReset => 'reset@example.com',
            AuthEmailType::PasswordChanged => 'changed@example.com',
        };
    }
}
