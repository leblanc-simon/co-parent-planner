<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\RefreshRequest;
use App\Dto\Auth\TokenResponse;
use App\Enum\AuditAction;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\InvalidRefreshTokenException;
use App\Security\Auth\Exception\RefreshReuseException;
use App\Security\Auth\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-AUTH-2 sc.5/6 — rafraîchissement rotatif (§3.3). Le refresh token vient du corps OU du
 * cookie HttpOnly dédié. Toute réutilisation détectée invalide la famille de tokens.
 *
 * @implements ProcessorInterface<RefreshRequest, TokenResponse>
 */
final class RefreshProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private readonly int $accessTokenTtl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenResponse
    {
        \assert($data instanceof RefreshRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();
        $plaintext = $data->refreshToken ?? $request?->cookies->get('refresh_token');

        if (null === $plaintext || '' === $plaintext) {
            throw new InvalidRefreshTokenException();
        }

        try {
            $rotated = $this->refreshTokens->rotate($plaintext);
        } catch (RefreshReuseException $e) {
            $this->audit->log(AuditAction::UserRefreshReuseDetected, ip: $ip);
            throw $e;
        }

        $this->em->flush();

        $accessToken = $this->jwtManager->create($rotated->user);
        $request?->attributes->set('_auth_tokens', ['access' => $accessToken, 'refresh' => $rotated->plaintext]);

        return new TokenResponse($accessToken, $rotated->plaintext, $this->accessTokenTtl);
    }
}
