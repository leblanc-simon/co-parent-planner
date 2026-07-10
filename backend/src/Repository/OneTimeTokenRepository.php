<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OneTimeToken;
use App\Entity\User;
use App\Enum\OneTimeTokenPurpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\Clock;

/**
 * @extends ServiceEntityRepository<OneTimeToken>
 */
class OneTimeTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OneTimeToken::class);
    }

    public function findOneByHashAndPurpose(string $tokenHash, OneTimeTokenPurpose $purpose): ?OneTimeToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash, 'purpose' => $purpose]);
    }

    /**
     * Jeton utilisable (non consommé, non expiré) d'un utilisateur pour une finalité — SANS le consommer.
     * Sert la validation du grant step-up admin (réutilisable dans sa fenêtre de fraîcheur, Lot 8 §6.2).
     */
    public function findUsableForUser(User $user, string $tokenHash, OneTimeTokenPurpose $purpose): ?OneTimeToken
    {
        $token = $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.purpose = :purpose')
            ->andWhere('t.consumedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('hash', $tokenHash)
            ->setParameter('purpose', $purpose)
            ->setParameter('now', Clock::get()->now())
            ->getQuery()
            ->getOneOrNullResult();

        return $token instanceof OneTimeToken ? $token : null;
    }

    public function countExpiredBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Purge des jetons à usage unique expirés (§3.4). Idempotent. */
    public function deleteExpiredBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /** Invalide (consomme) les jetons non consommés d'un utilisateur pour une finalité donnée. */
    public function consumePreviousFor(User $user, OneTimeTokenPurpose $purpose): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->andWhere('t.user = :user')
            ->andWhere('t.purpose = :purpose')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Clock::get()->now())
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->execute();
    }
}
