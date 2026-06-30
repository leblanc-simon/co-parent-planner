<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Family;
use App\Entity\FamilyLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<FamilyLink>
 */
class FamilyLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FamilyLink::class);
    }

    /** Lien existant pour la paire {a, b}, quel que soit le sens (clé canonique — §3.6). */
    public function findByPair(Uuid $a, Uuid $b): ?FamilyLink
    {
        [$low, $high] = FamilyLink::canonicalPair($a, $b);

        return $this->findOneBy(['pairLow' => $low, 'pairHigh' => $high]);
    }

    /**
     * Tous les liens (pending + active) impliquant la famille (dans un sens ou l'autre).
     *
     * @return list<FamilyLink>
     */
    public function findForFamily(Family $family): array
    {
        /** @var list<FamilyLink> $result */
        $result = $this->createQueryBuilder('l')
            ->andWhere('l.family = :family OR l.linkedFamily = :family')
            ->setParameter('family', $family)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
