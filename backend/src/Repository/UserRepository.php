<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** Recherche par e-mail (insensible à la casse via citext). L'entrée est normalisée. */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Comptes actifs inactifs au-delà du seuil (dernière connexion antérieure), candidats à
     * l'anonymisation par la purge (§3.4). Les admins techniques sont exclus (opérateurs, jamais
     * purgés) ; le garde-fou du dernier admin de famille est appliqué à l'anonymisation.
     *
     * @return list<User>
     */
    public function findInactiveCandidates(\DateTimeImmutable $before, int $limit = 500): array
    {
        /** @var list<User> $result */
        $result = $this->createQueryBuilder('u')
            ->andWhere('u.status = :active')
            ->andWhere('u.technicalAdmin = false')
            ->andWhere('u.lastLoginAt IS NOT NULL')
            ->andWhere('u.lastLoginAt < :before')
            ->setParameter('active', UserStatus::Active)
            ->setParameter('before', $before)
            ->orderBy('u.lastLoginAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
