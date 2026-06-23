<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\VerifyEmailRequest;
use App\Dto\MessageResponse;
use App\Enum\AuditAction;
use App\Enum\OneTimeTokenPurpose;
use App\Enum\UserStatus;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\OneTimeTokenManager;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * US-AUTH-1 sc.5/6 — vérification d'e-mail (§3.1). Token usage unique : active le compte.
 *
 * @implements ProcessorInterface<VerifyEmailRequest, MessageResponse>
 */
final class VerifyEmailProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OneTimeTokenManager $oneTimeTokens,
        private readonly AuditLogger $audit,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResponse
    {
        \assert($data instanceof VerifyEmailRequest);

        $user = $this->oneTimeTokens->consume($data->token, OneTimeTokenPurpose::EmailVerification);
        $user->setEmailVerifiedAt(Clock::get()->now())
            ->setStatus(UserStatus::Active);

        $this->audit->log(AuditAction::UserEmailVerified, $user, ip: $this->requestStack->getCurrentRequest()?->getClientIp());

        return new MessageResponse($this->translator->trans('auth.email_verified'));
    }
}
