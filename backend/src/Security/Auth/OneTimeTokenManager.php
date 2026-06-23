<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Entity\OneTimeToken;
use App\Entity\User;
use App\Enum\OneTimeTokenPurpose;
use App\Repository\OneTimeTokenRepository;
use App\Security\Auth\Exception\InvalidOneTimeTokenException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;

/**
 * Jetons à usage unique (vérification e-mail, reset mot de passe). Stockés hachés, expirants
 * (§3.1, §3.4). Émettre un nouveau jeton **consomme les précédents** de même finalité.
 * Les méthodes persistent ; l'appelant effectue le flush.
 */
final class OneTimeTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OneTimeTokenRepository $repository,
        private readonly TokenHasher $hasher,
        private readonly int $oneTimeTokenTtl,
    ) {
    }

    /** Émet un jeton clair et persiste son empreinte. Invalide les jetons antérieurs de même finalité. */
    public function issue(User $user, OneTimeTokenPurpose $purpose): string
    {
        $this->repository->consumePreviousFor($user, $purpose);

        $plaintext = $this->hasher->generate();
        $token = new OneTimeToken();
        $token->setUser($user)
            ->setPurpose($purpose)
            ->setTokenHash($this->hasher->hash($plaintext))
            ->setExpiresAt(Clock::get()->now()->add(new \DateInterval('PT'.$this->oneTimeTokenTtl.'S')));

        $this->em->persist($token);

        return $plaintext;
    }

    /**
     * Consomme un jeton (usage unique). Retourne l'utilisateur associé.
     *
     * @throws InvalidOneTimeTokenException introuvable, expiré ou déjà consommé
     */
    public function consume(string $plaintext, OneTimeTokenPurpose $purpose): User
    {
        $token = $this->repository->findOneByHashAndPurpose($this->hasher->hash($plaintext), $purpose);
        if (null === $token || !$token->isUsable()) {
            throw new InvalidOneTimeTokenException();
        }

        $token->consume();

        return $token->getUser();
    }
}
