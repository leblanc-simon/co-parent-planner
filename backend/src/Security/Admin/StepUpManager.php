<?php

declare(strict_types=1);

namespace App\Security\Admin;

use App\Entity\OneTimeToken;
use App\Entity\User;
use App\Enum\OneTimeTokenPurpose;
use App\Repository\OneTimeTokenRepository;
use App\Security\Auth\TokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jetons **step-up** d'auth renforcée pour l'administration technique RGPD (cdc §6.2).
 *
 * Émis après ré-authentification (mot de passe), stockés hachés, à TTL court (`RGPD_ADMIN_STEP_UP_TTL`).
 * Contrairement aux OTT de vérification/reset, ils sont **validés sans être consommés** : réutilisables
 * dans leur fenêtre de fraîcheur pour enchaîner plusieurs actions admin. Émettre un nouveau grant invalide
 * les précédents. La persistance est faite ici ; l'appelant effectue le flush.
 */
final class StepUpManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OneTimeTokenRepository $repository,
        private readonly TokenHasher $hasher,
        #[Autowire('%env(int:RGPD_ADMIN_STEP_UP_TTL)%')]
        private readonly int $stepUpTtl,
    ) {
    }

    /** Émet un grant step-up (clair) après ré-authentification réussie. */
    public function issue(User $user): string
    {
        $this->repository->consumePreviousFor($user, OneTimeTokenPurpose::AdminStepUp);

        $plaintext = $this->hasher->generate();
        $token = (new OneTimeToken())
            ->setUser($user)
            ->setPurpose(OneTimeTokenPurpose::AdminStepUp)
            ->setTokenHash($this->hasher->hash($plaintext))
            ->setExpiresAt(Clock::get()->now()->add(new \DateInterval('PT'.$this->stepUpTtl.'S')));

        $this->em->persist($token);

        return $plaintext;
    }

    /** Grant step-up frais et valide pour cet utilisateur ? (non consommant). */
    public function isValid(User $user, string $plaintext): bool
    {
        return null !== $this->repository->findUsableForUser(
            $user,
            $this->hasher->hash($plaintext),
            OneTimeTokenPurpose::AdminStepUp,
        );
    }

    public function ttlSeconds(): int
    {
        return $this->stepUpTtl;
    }
}
