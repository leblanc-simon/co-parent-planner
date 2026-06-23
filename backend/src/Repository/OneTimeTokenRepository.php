<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OneTimeToken;
use App\Entity\User;
use App\Enum\OneTimeTokenPurpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\Clock;

/**
 * @extends ServiceEntityRepository<OneTimeToken>
 */
class OneTimeTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OneTimeToken::class);
    }

    public function findOneByHashAndPurpose(string $tokenHash, OneTimeTokenPurpose $purpose): ?OneTimeToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash, 'purpose' => $purpose]);
    }

    /** Invalide (consomme) les jetons non consommés d'un utilisateur pour une finalité donnée. */
    public function consumePreviousFor(User $user, OneTimeTokenPurpose $purpose): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.consumedAt', ':now')
            ->andWhere('t.user = :user')
            ->andWhere('t.purpose = :purpose')
            ->andWhere('t.consumedAt IS NULL')
            ->setParameter('now', Clock::get()->now())
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->execute();
    }
}
