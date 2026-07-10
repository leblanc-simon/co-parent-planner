<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPref;
use App\Entity\User;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationPref>
 */
class NotificationPrefRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPref::class);
    }

    public function findOneFor(User $user, NotificationCategory $category, NotificationChannel $channel): ?NotificationPref
    {
        return $this->findOneBy(['user' => $user, 'category' => $category, 'channel' => $channel]);
    }

    /**
     * Préférences de l'utilisateur (pour l'export RGPD).
     *
     * @return list<NotificationPref>
     */
    public function findForUser(User $user): array
    {
        /** @var list<NotificationPref> $result */
        $result = $this->findBy(['user' => $user]);

        return $result;
    }

    public function countForUser(User $user): int
    {
        return (int) $this->count(['user' => $user]);
    }

    /** Supprime les préférences d'un utilisateur (anonymisation/effacement). Retourne le nombre supprimé. */
    public function deleteForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Préférences e-mail explicites de l'utilisateur, indexées par valeur de catégorie.
     *
     * @return array<string, bool>
     */
    public function emailMap(User $user): array
    {
        $map = [];
        foreach ($this->findBy(['user' => $user, 'channel' => NotificationChannel::Email]) as $pref) {
            $map[$pref->getCategory()->value] = $pref->isEnabled();
        }

        return $map;
    }

    /**
     * Notification e-mail activée pour cette catégorie ? Absence d'enregistrement ⇒ **activé**
     * (opt-out, §3.6).
     */
    public function isEmailEnabled(User $user, NotificationCategory $category): bool
    {
        $pref = $this->findOneFor($user, $category, NotificationChannel::Email);

        return null === $pref || $pref->isEnabled();
    }
}
