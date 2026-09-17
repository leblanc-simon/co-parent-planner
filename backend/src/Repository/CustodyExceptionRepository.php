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
     * Exceptions portant **exactement** la plage [start, end].
     *
     * Sert à ce qu'un second échange sur le même jour REMPLACE le premier au lieu de l'empiler :
     * deux exceptions superposées attribuent le même jour à deux parents, et c'est l'ordre
     * d'application du moteur — non l'intention de l'utilisateur — qui départage.
     *
     * @return list<CustodyException>
     */
    public function findByExactRange(Family $family, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var list<CustodyException> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->andWhere('e.startDate = :start')
            ->andWhere('e.endDate = :end')
            ->setParameter('family', $family)
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->orderBy('e.startDate', 'ASC')
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
