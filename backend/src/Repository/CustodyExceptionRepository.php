<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustodyException;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustodyException>
 */
class CustodyExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustodyException::class);
    }

    /** @return list<CustodyException> */
    public function findByFamily(Family $family): array
    {
        /** @var list<CustodyException> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->setParameter('family', $family)
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Exceptions chevauchant la fenêtre [from, to].
     *
     * @return list<CustodyException>
     */
    public function findOverlapping(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<CustodyException> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->andWhere('e.startDate <= :to')
            ->andWhere('e.endDate >= :from')
            ->setParameter('family', $family)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('e.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
