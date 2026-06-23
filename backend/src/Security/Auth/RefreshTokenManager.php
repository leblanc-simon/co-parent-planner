<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use App\Security\Auth\Exception\InvalidRefreshTokenException;
use App\Security\Auth\Exception\RefreshReuseException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Refresh tokens rotatifs avec détection de réutilisation (§3.3, décision D3).
 *
 * Invariant : un refresh token ne sert qu'une fois. La rotation révoque l'ancien et en émet un
 * nouveau dans la **même famille** ; toute réutilisation d'un token révoqué invalide la famille.
 * Les méthodes persistent ; l'appelant (processor) effectue le flush final.
 */
final class RefreshTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repository,
        private readonly TokenHasher $hasher,
        private readonly int $refreshTokenTtl,
    ) {
    }

    /** Émet un nouveau refresh token (nouvelle famille si non fournie). Retourne le jeton clair. */
    public function issue(User $user, ?Uuid $familyId = null): string
    {
        $plaintext = $this->hasher->generate();

        $token = new RefreshToken();
        $token->setUser($user)
            ->setTokenHash($this->hasher->hash($plaintext))
            ->setExpiresAt($this->expiry());
        if (null !== $familyId) {
            $token->setTokenFamilyId($familyId);
        }

        $this->em->persist($token);

        return $plaintext;
    }

    /**
     * Rotation : valide le jeton, détecte la réutilisation, en émet un nouveau dans la même famille.
     *
     * @throws RefreshReuseException        jeton déjà révoqué (rejeu) → famille invalidée
     * @throws InvalidRefreshTokenException jeton introuvable ou expiré
     */
    public function rotate(string $plaintext): RotatedToken
    {
        $token = $this->repository->findOneByHash($this->hasher->hash($plaintext));
        if (null === $token) {
            throw new InvalidRefreshTokenException();
        }

        if ($token->isRevoked()) {
            // Rejeu d'un jeton déjà tourné : on coupe toute la session (défense en profondeur).
            $this->repository->revokeFamily($token->getTokenFamilyId());
            throw new RefreshReuseException();
        }

        if ($token->isExpired()) {
            throw new InvalidRefreshTokenException();
        }

        $newPlaintext = $this->hasher->generate();
        $newHash = $this->hasher->hash($newPlaintext);

        $rotated = new RefreshToken();
        $rotated->setUser($token->getUser())
            ->setTokenHash($newHash)
            ->setTokenFamilyId($token->getTokenFamilyId())
            ->setExpiresAt($this->expiry());

        $token->revoke($newHash);
        $this->em->persist($rotated);

        return new RotatedToken($token->getUser(), $newPlaintext);
    }

    /** Révoque le refresh token courant (déconnexion). No-op si introuvable. */
    public function revoke(string $plaintext): void
    {
        $token = $this->repository->findOneByHash($this->hasher->hash($plaintext));
        $token?->revoke();
    }

    /** Révoque tous les refresh tokens d'un utilisateur (reset mot de passe, suppression). */
    public function revokeAllForUser(User $user): void
    {
        $this->repository->revokeAllForUser($user);
    }

    private function expiry(): \DateTimeImmutable
    {
        return Clock::get()->now()->add(new \DateInterval('PT'.$this->refreshTokenTtl.'S'));
    }
}
