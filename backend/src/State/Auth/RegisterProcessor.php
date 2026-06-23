<?php

declare(strict_types=1);

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Auth\RegisterRequest;
use App\Dto\MessageResponse;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\OneTimeTokenPurpose;
use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\Security\Auth\OneTimeTokenManager;
use App\Security\Encryption\FieldEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * US-AUTH-1 — inscription (§3.1). Anti-énumération : réponse identique que l'e-mail existe ou non ;
 * aucun token délivré (décision D1) ; e-mail de vérification envoyé en asynchrone.
 *
 * @implements ProcessorInterface<RegisterRequest, MessageResponse>
 */
final class RegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly OneTimeTokenManager $oneTimeTokens,
        private readonly AuditLogger $audit,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiterFactoryInterface $registerLimiter,
        private readonly FieldEncryptor $encryptor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResponse
    {
        \assert($data instanceof RegisterRequest);

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();

        $limit = $this->registerLimiter->create($ip ?? 'anon')->consume(1);
        if (!$limit->isAccepted()) {
            throw new TooManyAttemptsException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $email = mb_strtolower(trim($data->email));
        $generic = new MessageResponse($this->translator->trans('auth.registered'));

        if (null !== $this->users->findOneByEmail($email)) {
            // E-mail déjà utilisé : réponse non énumérante, aucun second compte créé.
            return $generic;
        }

        $user = new User();
        $user->setEmail($email)
            ->setPasswordHash($this->passwordHasher->hashPassword($user, $data->password))
            ->setFullName($data->fullName)
            ->setPhone($data->phone)
            ->setLocale($data->locale ?? 'fr-FR');
        $this->em->persist($user);

        $token = $this->oneTimeTokens->issue($user, OneTimeTokenPurpose::EmailVerification);
        $this->audit->log(AuditAction::UserRegistered, $user, ip: $ip); // flush

        // Jeton chiffré en transit : il ne transite jamais en clair dans la file Messenger.
        $this->bus->dispatch(new SendAuthEmail($user->getEmail(), $user->getLocale(), AuthEmailType::Verification, $this->encryptor->encrypt($token)));

        return $generic;
    }
}
