<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
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

    /**
     * Membres actifs d'une famille (liste des membres US-MBR-1), parents/admins d'abord.
     *
     * @return list<Membership>
     */
    public function findActiveByFamily(Family $family): array
    {
        /** @var list<Membership> $result */
        $result = $this->createQueryBuilder('m')
            ->andWhere('m.family = :family')
            ->andWhere('m.status = :status')
            ->setParameter('family', $family)
            ->setParameter('status', MembershipStatus::Active)
            ->orderBy('m.isAdmin', 'DESC')
            ->addOrderBy('m.createdAt', 'ASC')
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

    /**
     * Verrouille (SELECT … FOR UPDATE) les admins actifs de la famille et les compte (§3.2).
     * Sérialise les retraits/rétrogradations concurrents. **À appeler dans une transaction.**.
     */
    public function lockAndCountActiveAdmins(Family $family): int
    {
        $admins = $this->createQueryBuilder('m')
            ->andWhere('m.family = :family')
            ->andWhere('m.isAdmin = true')
            ->andWhere('m.status = :status')
            ->setParameter('family', $family)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        return \is_array($admins) ? \count($admins) : 0;
    }

    /** Un membership (compte-enfant) est-il rattaché à ce profil enfant ? (garde-fou suppression §4.2) */
    public function existsForChild(Uuid $childId): bool
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.childId = :child')
            ->setParameter('child', $childId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** Membership actif ciblant un e-mail dans une famille (via l'utilisateur du compte). */
    public function findActiveByEmail(Family $family, string $email): ?Membership
    {
        return $this->createQueryBuilder('m')
            ->join('m.user', 'u')
            ->andWhere('m.family = :family')
            ->andWhere('u.email = :email')
            ->andWhere('m.status = :status')
            ->setParameter('family', $family)
            ->setParameter('email', $email)
            ->setParameter('status', MembershipStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Conjoint d'un membre, **quel que soit le côté qui porte la relation** (évolution E1).
     *
     * La colonne `partner_membership_id` est portée par le beau-parent ; demander « qui accompagne
     * ce parent ? » impose donc la requête inverse. Les appelants ne doivent pas avoir à savoir de
     * quel côté ils se trouvent — c'est exactement l'erreur que `PartnerWriter` évite déjà en
     * écriture.
     *
     * Seul un membre **actif** est rendu : nommer un foyer avec quelqu'un qui a quitté la famille
     * dirait faux. (La suppression d'un membership passe le `SET NULL`, mais un statut non actif
     * survivrait à la colonne.)
     */
    public function partnerOf(Membership $member): ?Membership
    {
        $partner = $member->getPartnerMembership()
            ?? $this->findOneBy(['partnerMembership' => $member]);

        if (!$partner instanceof Membership || MembershipStatus::Active !== $partner->getStatus()) {
            return null;
        }

        return $partner;
    }
}
