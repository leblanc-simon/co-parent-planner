<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\TokenResponse;
use App\Dto\Invitation\RegisterAndAcceptInvitationRequest;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserStatus;
use App\Exception\AccountAlreadyExistsException;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
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
 * Flux d'invitation simplifié (§3.3) — création de compte + acceptation + ouverture de session, en
 * une seule transaction. Le token d'invitation prouve la possession de la boîte mail : le compte est
 * créé `active` avec `emailVerifiedAt=now` (décision « possession du token = e-mail vérifié », chemin
 * invitation uniquement — {@see \App\State\Auth\RegisterProcessor} reste inchangé pour l'inscription
 * libre). L'e-mail est dérivé de l'invitation, jamais du formulaire → aucun mismatch possible.
 *
 * @implements ProcessorInterface<RegisterAndAcceptInvitationRequest, TokenResponse>
 */
final class RegisterAndAcceptInvitationProcessor implements ProcessorInterface
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
        private readonly RateLimiterFactoryInterface $registerLimiter,
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private readonly int $accessTokenTtl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenResponse
    {
        \assert($data instanceof RegisterAndAcceptInvitationRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();

        $limit = $this->registerLimiter->create($ip ?? 'anon')->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - time());
        }

        /** @var array{access: string, refresh: string} $tokens */
        $tokens = $this->em->wrapInTransaction(function () use ($data, $ip): array {
            $invitation = $this->accepter->loadPendingForUpdate($data->token);

            // L'e-mail est dérivé de l'invitation (jamais du client) : le mismatch est structurellement
            // impossible et le champ readonly du formulaire n'est qu'ergonomique.
            $email = mb_strtolower(trim($invitation->getEmail()));

            // Défensif : un compte existe déjà → le chemin correct est login-and-accept.
            if (null !== $this->users->findOneByEmail($email)) {
                throw new AccountAlreadyExistsException();
            }

            $user = new User();
            $user->setEmail($email)
                ->setPasswordHash($this->passwordHasher->hashPassword($user, $data->password))
                ->setFullName($data->fullName)
                ->setPhone($data->phone)
                ->setLocale($data->locale ?? 'fr-FR')
                ->setStatus(UserStatus::Active)
                ->setEmailVerifiedAt(Clock::get()->now())
                ->setLastLoginAt(Clock::get()->now());
            $this->em->persist($user);

            $this->accepter->accept($invitation, $user, $ip);

            $this->audit->persist(AuditAction::UserRegistered, $user, ip: $ip, metadata: ['via' => 'invitation']);
            $this->audit->persist(AuditAction::UserEmailVerified, $user, ip: $ip, metadata: ['reason' => 'invitation_token_proof']);

            // Session ouverte immédiatement : jetons émis dans la transaction (RefreshTokenManager
            // persiste sans flush ; wrapInTransaction flushe au commit).
            return [
                'access' => $this->jwtManager->create($user),
                'refresh' => $this->refreshTokens->issue($user),
            ];
        });

        // Cookies HttpOnly posés par AuthCookieSubscriber (web) ; corps consommé par le mobile.
        $request?->attributes->set('_auth_tokens', $tokens);

        return new TokenResponse($tokens['access'], $tokens['refresh'], $this->accessTokenTtl);
    }
}
