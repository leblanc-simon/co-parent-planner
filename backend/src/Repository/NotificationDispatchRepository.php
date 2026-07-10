<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationDispatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDispatch>
 */
class NotificationDispatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDispatch::class);
    }

    public function findOneByKey(string $notificationKey): ?NotificationDispatch
    {
        return $this->findOneBy(['notificationKey' => $notificationKey]);
    }

    public function countOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Purge des journaux d'envoi au-delà de la rétention (§3.4). Idempotent. */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
