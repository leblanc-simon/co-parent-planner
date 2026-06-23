<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\LogoutRequest;
use App\Dto\MessageResponse;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * US-AUTH-2 sc.7 — déconnexion (§3). Révoque le refresh token courant (corps ou cookie) et
 * demande l'effacement des cookies d'auth (décision D2).
 *
 * @implements ProcessorInterface<LogoutRequest, MessageResponse>
 */
final class LogoutProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly AuditLogger $audit,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResponse
    {
        \assert($data instanceof LogoutRequest);

        $request = $this->requestStack->getCurrentRequest();
        $plaintext = $data->refreshToken ?? $request?->cookies->get('refresh_token');
        if (null !== $plaintext && '' !== $plaintext) {
            $this->refreshTokens->revoke($plaintext);
            $this->em->flush();
        }

        $user = $this->security->getUser();
        $this->audit->log(
            AuditAction::UserLogout,
            $user instanceof User ? $user : null,
            ip: $request?->getClientIp(),
        );

        $request?->attributes->set('_auth_clear_cookies', true);

        return new MessageResponse($this->translator->trans('auth.logged_out'));
    }
}
