<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Child>
 */
class ChildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Child::class);
    }

    /**
     * Enfants de la famille (scopé), triés par prénom.
     *
     * @return list<Child>
     */
    public function findByFamily(Family $family): array
    {
        /** @var list<Child> $result */
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            ->orderBy('c.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
