<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Journal filtré par action (rapport admin US-RGPD-2 — corrélation aux opérations RGPD),
     * du plus récent au plus ancien.
     *
     * @param list<AuditAction> $actions
     *
     * @return list<AuditLog>
     */
    public function findByActions(array $actions, int $limit, int $offset): array
    {
        if ([] === $actions) {
            return [];
        }

        /** @var list<AuditLog> $result */
        $result = $this->createQueryBuilder('a')
            ->andWhere('a.action IN (:actions)')
            ->setParameter('actions', $actions)
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->setFirstResult(max(0, $offset))
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Entrées de journal antérieures au seuil de rétention (purge des logs §3.4). */
    public function countOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.occurredAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Supprime les entrées au-delà de la rétention (sans toucher aux plus récentes). Idempotent. */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.occurredAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
