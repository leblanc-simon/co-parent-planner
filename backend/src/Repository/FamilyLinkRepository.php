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
     * Lien **actif** entre la famille active et une autre famille (overlay Lot 5 §3.2). Renvoie null
     * si aucun lien, lien `pending`, ou `otherId` = la famille elle-même (overlay sur soi interdit).
     */
    public function findActiveBetween(Family $family, Uuid $otherId): ?FamilyLink
    {
        if ($family->getId()->equals($otherId)) {
            return null;
        }
        $link = $this->findByPair($family->getId(), $otherId);

        return (null !== $link && $link->getStatus()->isActive()) ? $link : null;
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
