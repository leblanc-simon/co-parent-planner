<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\ForgotPasswordRequest;
use App\Dto\MessageResponse;
use App\Enum\AuditAction;
use App\Enum\OneTimeTokenPurpose;
use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\Security\Auth\OneTimeTokenManager;
use App\Security\Encryption\FieldEncryptor;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * US-AUTH-2 sc.8 — demande de reset (§3.4). Réponse TOUJOURS générique (anti-énumération),
 * rate-limitée ; e-mail envoyé en asynchrone uniquement si le compte existe.
 *
 * @implements ProcessorInterface<ForgotPasswordRequest, MessageResponse>
 */
final class ForgotPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly OneTimeTokenManager $oneTimeTokens,
        private readonly AuditLogger $audit,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $forgotLimiter,
        private readonly FieldEncryptor $encryptor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResponse
    {
        \assert($data instanceof ForgotPasswordRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();
        $email = mb_strtolower(trim($data->email));

        $limit = $this->forgotLimiter->create(($ip ?? 'anon').'|'.$email)->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - Clock::get()->now()->getTimestamp());
        }

        $user = $this->users->findOneByEmail($email);
        // Seuls les comptes authentifiables (actifs) peuvent réinitialiser leur mot de passe ;
        // un compte non vérifié doit d'abord vérifier son e-mail, un compte suspendu reste bloqué.
        if (null !== $user && $user->getStatus()->canAuthenticate()) {
            $token = $this->oneTimeTokens->issue($user, OneTimeTokenPurpose::PasswordReset);
            $this->audit->log(AuditAction::UserPasswordResetRequested, $user, ip: $ip); // flush
            // Jeton chiffré en transit (jamais en clair dans la file Messenger).
            $this->bus->dispatch(new SendAuthEmail($user->getEmail(), $user->getLocale(), AuthEmailType::PasswordReset, $this->encryptor->encrypt($token)));
        }

        // Réponse identique que le compte existe ou non.
        return new MessageResponse($this->translator->trans('auth.forgot_sent'));
    }
}
