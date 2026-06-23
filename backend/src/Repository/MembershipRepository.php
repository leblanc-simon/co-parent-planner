<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /** Membership ACTIF (user, family) — base du scope X-Family-Id (§3.6). */
    public function findActive(User $user, Uuid $familyId): ?Membership
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('IDENTITY(m.family) = :family')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('family', $familyId, 'uuid')
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Memberships actifs de l'utilisateur (pour le sélecteur de famille / claims Mercure).
     *
     * @return list<Membership>
     */
    public function findActiveForUser(User $user): array
    {
        /** @var list<Membership> $result */
        $result = $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MembershipStatus::Active)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Nombre d'admins actifs d'une famille (protection du dernier admin — invariant). */
    public function countActiveAdmins(Family $family): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.family = :family')
            ->andWhere('m.isAdmin = true')
            ->andWhere('m.status = :status')
            ->setParameter('family', $family)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
