<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustodyDay;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustodyDay>
 */
class CustodyDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustodyDay::class);
    }

    /**
     * Jours effectifs sur une fenêtre [from, to] (bornes incluses), triés par date.
     *
     * @return list<CustodyDay>
     */
    public function findRange(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<CustodyDay> $result */
        $result = $this->createQueryBuilder('d')
            ->andWhere('d.family = :family')
            ->andWhere('d.date >= :from')
            ->andWhere('d.date <= :to')
            ->setParameter('family', $family)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
