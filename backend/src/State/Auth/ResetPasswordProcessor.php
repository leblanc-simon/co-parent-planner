<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\ResetPasswordRequest;
use App\Dto\MessageResponse;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\OneTimeTokenPurpose;
use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\OneTimeTokenManager;
use App\Security\Auth\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * US-AUTH-2 sc.9/10 — réinitialisation effective (§3.4). Token usage unique ; remplace le hash ;
 * révoque TOUS les refresh tokens (déconnexion globale) ; e-mail de confirmation.
 *
 * @implements ProcessorInterface<ResetPasswordRequest, MessageResponse>
 */
final class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OneTimeTokenManager $oneTimeTokens,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly AuditLogger $audit,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResponse
    {
        \assert($data instanceof ResetPasswordRequest);

        // Atomicité : changement de mot de passe + consommation du jeton + révocation des refresh
        // dans une même transaction, pour ne jamais révoquer les sessions sans changer le mot de passe.
        $user = $this->em->wrapInTransaction(function () use ($data) {
            $user = $this->oneTimeTokens->consume($data->token, OneTimeTokenPurpose::PasswordReset);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $data->password));
            $this->em->flush();
            $this->refreshTokens->revokeAllForUser($user);

            return $user;
        });
        \assert($user instanceof User);

        $this->audit->log(AuditAction::UserPasswordReset, $user, ip: $this->requestStack->getCurrentRequest()?->getClientIp());

        $this->bus->dispatch(new SendAuthEmail($user->getEmail(), $user->getLocale(), AuthEmailType::PasswordChanged));

        return new MessageResponse($this->translator->trans('auth.password_reset_done'));
    }
}
