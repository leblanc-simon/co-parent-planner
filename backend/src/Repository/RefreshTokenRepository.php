<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findOneByHash(string $tokenHash): ?RefreshToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** Révoque toute la famille de tokens (détection de réutilisation — §3.3). */
    public function revokeFamily(Uuid $tokenFamilyId): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.revokedAt', ':now')
            ->andWhere('t.tokenFamilyId = :family')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('now', Clock::get()->now())
            ->setParameter('family', $tokenFamilyId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /** Révoque tous les refresh tokens d'un utilisateur (reset mot de passe, suppression). */
    public function revokeAllForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.revokedAt', ':now')
            ->andWhere('t.user = :user')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('now', Clock::get()->now())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
