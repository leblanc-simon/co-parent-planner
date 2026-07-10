<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Invitation;
use App\Enum\InvitationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\Clock;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?Invitation
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Lookup verrouillé (SELECT … FOR UPDATE) pour l'acceptation : sérialise les acceptations
     * concurrentes du même token (§4.2). **À appeler dans une transaction.**.
     */
    public function findOneByTokenHashForUpdate(string $tokenHash): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.tokenHash = :h')
            ->setParameter('h', $tokenHash)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /** Invitation `pending` (quelle qu'en soit l'expiration) pour un couple (famille, email_hash) — §3.3. */
    public function findPending(Family $family, string $emailHash): ?Invitation
    {
        return $this->findOneBy([
            'family' => $family,
            'emailHash' => $emailHash,
            'status' => InvitationStatus::Pending,
        ]);
    }

    /**
     * Invitations `pending` d'une famille (volet admin US-INV-2), les plus récentes d'abord.
     *
     * @return list<Invitation>
     */
    public function findPendingForFamily(Family $family): array
    {
        /** @var list<Invitation> $result */
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.family = :family')
            ->andWhere('i.status = :status')
            ->setParameter('family', $family)
            ->setParameter('status', InvitationStatus::Pending)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Marque `expired` les invitations `pending` dont l'échéance est passée (purge planifiée §3.3.E).
     * Idempotent ; renvoie le nombre d'invitations marquées.
     */
    public function markExpired(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->update()
            ->set('i.status', ':expired')
            ->andWhere('i.status = :pending')
            ->andWhere('i.expiresAt <= :now')
            ->setParameter('expired', InvitationStatus::Expired)
            ->setParameter('pending', InvitationStatus::Pending)
            ->setParameter('now', Clock::get()->now())
            ->getQuery()
            ->execute();
    }

    /** Invitations dont l'échéance est antérieure au seuil (purge RGPD §3.4). */
    public function countExpiredBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Supprime les invitations expirées au-delà de la rétention. Idempotent ; renvoie le nombre supprimé. */
    public function deleteExpiredBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('i')
            ->delete()
            ->andWhere('i.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime toutes les invitations adressées à un e-mail (via son blind index), quel que soit leur
     * statut. Utilisé par l'anonymisation : `Invitation.email` est chiffré **réversiblement** (clé
     * applicative) — le conserver rendrait l'effacement réversible (§4.8). Renvoie le nombre supprimé.
     */
    public function deleteByEmailHash(string $emailHash): int
    {
        return (int) $this->createQueryBuilder('i')
            ->delete()
            ->andWhere('i.emailHash = :hash')
            ->setParameter('hash', $emailHash)
            ->getQuery()
            ->execute();
    }
}
