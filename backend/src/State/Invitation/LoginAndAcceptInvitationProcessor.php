<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\TokenResponse;
use App\Dto\Invitation\LoginAndAcceptInvitationRequest;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserStatus;
use App\Exception\AccountRequiresRegistrationException;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\InvalidCredentialsException;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\Security\Auth\RefreshTokenManager;
use App\Security\Invitation\InvitationAccepter;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Flux d'invitation simplifié (§3.3), variante *compte existant* — connexion + acceptation +
 * ouverture de session, en une transaction. Le **mot de passe est exigé** (frontière de sécurité :
 * un lien transféré ne détourne jamais un compte tiers). Un compte `pending` (inscrit en self-serve,
 * jamais vérifié) est activé ici : le token prouve la possession de la boîte — mais on **n'écrase
 * jamais** le mot de passe existant (décision PO : prudence ; en cas d'oubli → mot de passe oublié).
 *
 * @implements ProcessorInterface<LoginAndAcceptInvitationRequest, TokenResponse>
 */
final class LoginAndAcceptInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly InvitationAccepter $accepter,
        private readonly AuditLogger $audit,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $loginLimiter,
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private readonly int $accessTokenTtl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenResponse
    {
        \assert($data instanceof LoginAndAcceptInvitationRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();

        // Anti brute-force du mot de passe sur ce chemin (l'e-mail n'est pas encore connu → clé IP).
        $limiter = $this->loginLimiter->create(($ip ?? 'anon').'|invitation');
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - Clock::get()->now()->getTimestamp());
        }

        /** @var array{access: string, refresh: string} $tokens */
        $tokens = $this->em->wrapInTransaction(function () use ($data, $ip): array {
            $invitation = $this->accepter->loadPendingForUpdate($data->token);
            $email = mb_strtolower(trim($invitation->getEmail()));

            $user = $this->users->findOneByEmail($email);
            // Défensif : aucun compte → le chemin correct est register-and-accept.
            if (!$user instanceof User) {
                throw new AccountRequiresRegistrationException();
            }

            // Mot de passe obligatoire (2ᵉ facteur préservé) — message générique non énumérant.
            if (!$this->passwordHasher->isPasswordValid($user, $data->password)) {
                throw new InvalidCredentialsException();
            }

            if (UserStatus::Pending === $user->getStatus()) {
                // Le token prouve la boîte : on active, sans jamais toucher au mot de passe existant.
                $user->setStatus(UserStatus::Active);
                if (!$user->isEmailVerified()) {
                    $user->setEmailVerifiedAt(Clock::get()->now());
                }
                $this->audit->persist(AuditAction::UserEmailVerified, $user, ip: $ip, metadata: ['reason' => 'invitation_token_proof']);
            } elseif (!$user->getStatus()->canAuthenticate()) {
                // Suspendu / supprimé : refus générique.
                throw new InvalidCredentialsException();
            }

            $this->accepter->accept($invitation, $user, $ip);

            $user->setLastLoginAt(Clock::get()->now());
            $this->audit->persist(AuditAction::UserLoginSuccess, $user, ip: $ip, metadata: ['via' => 'invitation']);

            return [
                'access' => $this->jwtManager->create($user),
                'refresh' => $this->refreshTokens->issue($user),
            ];
        });

        $limiter->reset();
        $request?->attributes->set('_auth_tokens', $tokens);

        return new TokenResponse($tokens['access'], $tokens['refresh'], $this->accessTokenTtl);
    }
}
