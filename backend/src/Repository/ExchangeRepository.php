<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Family;
use App\Enum\ExchangeStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exchange>
 */
class ExchangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exchange::class);
    }

    /**
     * Liste scopée famille, filtrée par statut. Tri : ouverts d'abord, échéance la plus proche en
     * tête (NULLS LAST), puis création décroissante (§3.6).
     *
     * @return list<Exchange>
     */
    public function listForFamily(Family $family, ?ExchangeStatus $status): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->setParameter('family', $family)
            ->addSelect('CASE WHEN e.status = :open THEN 0 ELSE 1 END AS HIDDEN openFirst')
            ->setParameter('open', ExchangeStatus::Open->value)
            ->orderBy('openFirst', 'ASC')
            ->addOrderBy('e.deadline', 'ASC')
            ->addOrderBy('e.createdAt', 'DESC');

        if (null !== $status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        /** @var list<Exchange> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findOneByIdempotencyKey(Family $family, string $key): ?Exchange
    {
        return $this->findOneBy(['family' => $family, 'idempotencyKey' => $key]);
    }

    /** Nombre d'échanges ouverts de la famille (badge §3.5). */
    public function countOpen(Family $family): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.family = :family')->setParameter('family', $family)
            ->andWhere('e.status = :open')->setParameter('open', ExchangeStatus::Open)
            ->getQuery()->getSingleScalarResult();
    }
}
