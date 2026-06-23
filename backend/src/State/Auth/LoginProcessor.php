<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\TokenResponse;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserStatus;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\EmailNotVerifiedException;
use App\Security\Auth\Exception\InvalidCredentialsException;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\Security\Auth\RefreshTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * US-AUTH-2 sc.1-4 — connexion (§3.2). Rate-limit + lockout par (IP, e-mail) ; vérification en
 * temps quasi-constant (hash factice si utilisateur absent) ; messages génériques non énumérants ;
 * refus si e-mail non vérifié (décision D1).
 *
 * @implements ProcessorInterface<LoginRequest, TokenResponse>
 */
final class LoginProcessor implements ProcessorInterface
{
    /** Empreinte Argon2id factice : équilibre le temps de réponse quand l'utilisateur n'existe pas. */
    private const string DUMMY_HASH = '$argon2id$v=19$m=65536,t=2,p=1$UtwTyYgXLvMiZ8y77/jCLg$NpU0IqAIQmhpiN6MaEc9mzlqS/7Mw0HZ1xu6SZpaJQ8';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $loginLimiter,
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private readonly int $accessTokenTtl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenResponse
    {
        \assert($data instanceof LoginRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();
        $email = mb_strtolower(trim($data->email));

        $limiter = $this->loginLimiter->create(($ip ?? 'anon').'|'.$email);
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $this->audit->log(AuditAction::UserLoginLocked, ip: $ip, metadata: ['email' => $email]);
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - Clock::get()->now()->getTimestamp());
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user instanceof User) {
            $valid = $this->passwordHasher->isPasswordValid($user, $data->password);
        } else {
            // Anti-oracle temporel : on vérifie une empreinte factice puis on échoue.
            $this->passwordHasher->isPasswordValid($this->dummyUser(), $data->password);
            $valid = false;
        }

        if (!$user instanceof User || !$valid) {
            $this->audit->log(AuditAction::UserLoginFailed, $user, ip: $ip, metadata: ['reason' => 'invalid_credentials']);
            throw new InvalidCredentialsException();
        }

        if (UserStatus::Pending === $user->getStatus()) {
            $this->audit->log(AuditAction::UserLoginFailed, $user, ip: $ip, metadata: ['reason' => 'email_not_verified']);
            throw new EmailNotVerifiedException();
        }

        if (!$user->getStatus()->canAuthenticate()) {
            $this->audit->log(AuditAction::UserLoginFailed, $user, ip: $ip, metadata: ['reason' => 'status_'.$user->getStatus()->value]);
            throw new InvalidCredentialsException();
        }

        // Succès : reset du compteur, émission des jetons.
        $limiter->reset();
        $user->setLastLoginAt(Clock::get()->now());
        $accessToken = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokens->issue($user);
        $this->audit->log(AuditAction::UserLoginSuccess, $user, ip: $ip); // flush

        $request?->attributes->set('_auth_tokens', ['access' => $accessToken, 'refresh' => $refreshToken]);

        return new TokenResponse($accessToken, $refreshToken, $this->accessTokenTtl);
    }

    private function dummyUser(): User
    {
        return (new User())
            ->setEmail('no-user@invalid.local')
            ->setPasswordHash(self::DUMMY_HASH);
    }
}
