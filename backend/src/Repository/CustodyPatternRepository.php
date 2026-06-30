<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustodyPattern;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustodyPattern>
 */
class CustodyPatternRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustodyPattern::class);
    }

    public function findOneByFamily(Family $family): ?CustodyPattern
    {
        return $this->findOneBy(['family' => $family]);
    }
}
