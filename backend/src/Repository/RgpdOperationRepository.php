<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RgpdOperation;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RgpdOperation>
 */
class RgpdOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RgpdOperation::class);
    }

    public function save(RgpdOperation $operation, bool $flush = true): void
    {
        $this->getEntityManager()->persist($operation);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Réclame atomiquement une opération pour traitement : passe `pending|failed` → `running` en une
     * seule requête. Renvoie `true` si CE worker l'a réclamée, `false` si un autre l'a déjà prise
     * (protège l'export contre un double traitement concurrent, redélivrance at-least-once).
     */
    public function claimForProcessing(Uuid $id): bool
    {
        $affected = (int) $this->createQueryBuilder('o')
            ->update()
            ->set('o.status', ':running')
            ->andWhere('o.id = :id')
            ->andWhere('o.status IN (:claimable)')
            ->setParameter('running', RgpdOperationStatus::Running)
            ->setParameter('id', $id, 'uuid')
            ->setParameter('claimable', [RgpdOperationStatus::Pending, RgpdOperationStatus::Failed])
            ->getQuery()
            ->execute();

        return 1 === $affected;
    }

    /**
     * Journal des opérations RGPD (US-RGPD-2), filtrable et paginé, du plus récent au plus ancien.
     *
     * @return list<RgpdOperation>
     */
    public function findForListing(?RgpdOperationType $type, ?RgpdOperationStatus $status, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('o')
            ->orderBy('o.occurredAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->setFirstResult(max(0, $offset));

        if (null !== $type) {
            $qb->andWhere('o.type = :type')->setParameter('type', $type);
        }
        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        /** @var list<RgpdOperation> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function countForListing(?RgpdOperationType $type, ?RgpdOperationStatus $status): int
    {
        $qb = $this->createQueryBuilder('o')->select('COUNT(o.id)');
        if (null !== $type) {
            $qb->andWhere('o.type = :type')->setParameter('type', $type);
        }
        if (null !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Exports finalisés dont l'artefact a dépassé le TTL du lien signé (purge des exports temporaires).
     *
     * @return list<RgpdOperation>
     */
    public function findExpiredExports(\DateTimeImmutable $before): array
    {
        /** @var list<RgpdOperation> $result */
        $result = $this->createQueryBuilder('o')
            ->andWhere('o.type = :type')->setParameter('type', RgpdOperationType::Export)
            ->andWhere('o.resultRef IS NOT NULL')
            ->andWhere('o.occurredAt < :before')->setParameter('before', $before)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
