<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FamilyLink;
use App\Entity\Membership;
use App\Entity\OverlayPreference;
use App\Enum\OverlayView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OverlayPreference>
 */
class OverlayPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OverlayPreference::class);
    }

    /**
     * Préférences overlay d'un membre (toutes vues/liens), pour l'ouverture d'une vue (§6.2).
     *
     * @return list<OverlayPreference>
     */
    public function findByMembership(Membership $membership): array
    {
        /** @var list<OverlayPreference> $result */
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.membership = :membership')
            ->setParameter('membership', $membership)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Préférence exacte du triplet (membre, lien, vue) — clé d'upsert (§6.2). */
    public function findOneFor(Membership $membership, FamilyLink $link, OverlayView $view): ?OverlayPreference
    {
        return $this->findOneBy(['membership' => $membership, 'familyLink' => $link, 'view' => $view]);
    }
}
