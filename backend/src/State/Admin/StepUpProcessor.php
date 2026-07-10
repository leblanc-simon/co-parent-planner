<?php

declare(strict_types=1);

namespace App\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Admin\AdminStepUp;
use App\Dto\Admin\StepUpRequest;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Security\Admin\StepUpManager;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\InvalidCredentialsException;
use App\Security\Auth\Exception\TooManyAttemptsException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Ré-authentification (mot de passe) émettant un grant step-up (cdc §6.2). Réservé à ROLE_ADMIN
 * (access_control) ; ne requiert PAS lui-même un step-up (c'est le point d'entrée de l'auth renforcée).
 *
 * @implements ProcessorInterface<StepUpRequest, AdminStepUp>
 */
final class StepUpProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly StepUpManager $stepUp,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $adminStepUpLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AdminStepUp
    {
        \assert($data instanceof StepUpRequest);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        // Anti brute-force d'une session admin volée (JWT sans mot de passe) : verrou par (IP, user).
        $limiter = $this->adminStepUpLimiter->create(($ip ?? 'anon').'|'.$user->getId());
        $limit = $limiter->consume(1);
        if (!$limit->isAccepted()) {
            $this->audit->log(AuditAction::AdminStepUpFailed, $user, ip: $ip, metadata: ['reason' => 'rate_limited']);
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - Clock::get()->now()->getTimestamp());
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data->password)) {
            $this->audit->log(AuditAction::AdminStepUpFailed, $user, ip: $ip);
            throw new InvalidCredentialsException();
        }

        $limiter->reset(); // succès : on relâche le compteur
        $token = $this->stepUp->issue($user);
        $this->em->flush();
        $this->audit->log(AuditAction::AdminStepUpGranted, $user, ip: $ip);

        return new AdminStepUp($token, $this->stepUp->ttlSeconds());
    }
}
