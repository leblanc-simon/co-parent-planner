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
