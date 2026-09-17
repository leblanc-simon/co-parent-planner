<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Family;
use App\Service\Child\ChildSorter;
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
     * Le tri est fait **en PHP** : `first_name` est chiffré au repos, un `ORDER BY` SQL trierait donc
     * des ciphertexts (cf. {@see ChildSorter}). Point de passage unique pour lister les enfants d'une
     * famille — les appelants qui triaient eux-mêmes reproduisaient le défaut.
     *
     * @return list<Child>
     */
    public function findByFamily(Family $family): array
    {
        /** @var list<Child> $result */
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();

        return ChildSorter::byFirstName($result);
    }
}
