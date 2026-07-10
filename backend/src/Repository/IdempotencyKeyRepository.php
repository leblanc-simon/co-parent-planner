<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IdempotencyKey;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdempotencyKey>
 */
class IdempotencyKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdempotencyKey::class);
    }

    public function findOneByUserAndKey(User $user, string $key): ?IdempotencyKey
    {
        return $this->findOneBy(['user' => $user, 'idempotencyKey' => $key]);
    }

    public function countOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('k')
            ->select('COUNT(k.id)')
            ->andWhere('k.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Purge des clés d'idempotence au-delà du TTL (§6.3). Idempotent. */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('k')
            ->delete()
            ->andWhere('k.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
