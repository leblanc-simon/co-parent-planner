<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeviceToken;
use App\Entity\User;
use App\Enum\DeviceTokenStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DeviceToken>
 */
class DeviceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceToken::class);
    }

    public function findActiveByTokenHash(string $tokenHash): ?DeviceToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash, 'status' => DeviceTokenStatus::Active]);
    }

    public function findActiveByInstallation(User $user, string $installationId): ?DeviceToken
    {
        return $this->findOneBy([
            'user' => $user,
            'installationId' => $installationId,
            'status' => DeviceTokenStatus::Active,
        ]);
    }

    /** Appareil de cet utilisateur (quel que soit son statut), sinon null — jamais celui d'un tiers. */
    public function findOneOwnedBy(User $user, Uuid $id): ?DeviceToken
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * Appareils actifs de l'utilisateur : cibles du fan-out push, et liste montrée à l'utilisateur.
     *
     * @return list<DeviceToken>
     */
    public function findActiveForUser(User $user): array
    {
        /** @var list<DeviceToken> $result */
        $result = $this->findBy(['user' => $user, 'status' => DeviceTokenStatus::Active], ['createdAt' => 'ASC']);

        return $result;
    }

    public function hasActiveForUser(User $user): bool
    {
        return $this->count(['user' => $user, 'status' => DeviceTokenStatus::Active]) > 0;
    }

    /**
     * Tous les appareils de l'utilisateur (export RGPD).
     *
     * @return list<DeviceToken>
     */
    public function findForUser(User $user): array
    {
        /** @var list<DeviceToken> $result */
        $result = $this->findBy(['user' => $user], ['createdAt' => 'ASC']);

        return $result;
    }

    /**
     * Supprime tous les appareils d'un utilisateur (effacement RGPD) : identifiants techniques sans
     * valeur pour la cohérence des fils, ils sont supprimés et non anonymisés (Lot 11 §4.8).
     */
    public function deleteForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('d')
            ->delete()
            ->andWhere('d.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Jetons purgeables (Lot 11 §5.4) : révoqués ou invalidés avant `$retiredBefore`, et jetons
     * encore actifs dont l'appareil ne s'est plus manifesté depuis `$dormantBefore`.
     */
    public function countPurgeable(\DateTimeImmutable $retiredBefore, \DateTimeImmutable $dormantBefore): int
    {
        return (int) $this->purgeable($this->createQueryBuilder('d')->select('COUNT(d.id)'), $retiredBefore, $dormantBefore)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Purge idempotente : relancée, elle ne retrouve plus rien. */
    public function deletePurgeable(\DateTimeImmutable $retiredBefore, \DateTimeImmutable $dormantBefore): int
    {
        return (int) $this->purgeable($this->createQueryBuilder('d')->delete(), $retiredBefore, $dormantBefore)
            ->getQuery()
            ->execute();
    }

    private function purgeable(QueryBuilder $qb, \DateTimeImmutable $retiredBefore, \DateTimeImmutable $dormantBefore): QueryBuilder
    {
        return $qb
            ->andWhere('(d.status = :revoked AND d.revokedAt < :retired)'
                .' OR (d.status = :invalid AND d.invalidatedAt < :retired)'
                .' OR (d.status = :active AND COALESCE(d.lastSeenAt, d.createdAt) < :dormant)')
            ->setParameter('revoked', DeviceTokenStatus::Revoked)
            ->setParameter('invalid', DeviceTokenStatus::Invalid)
            ->setParameter('active', DeviceTokenStatus::Active)
            ->setParameter('retired', $retiredBefore)
            ->setParameter('dormant', $dormantBefore);
    }
}
