<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationDispatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationDispatch>
 */
class NotificationDispatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationDispatch::class);
    }

    public function findOneByKey(string $notificationKey): ?NotificationDispatch
    {
        return $this->findOneBy(['notificationKey' => $notificationKey]);
    }
}
