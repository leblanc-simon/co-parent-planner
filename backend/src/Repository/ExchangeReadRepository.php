<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\ExchangeRead;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Dernières lectures d'échanges, **du seul membre courant** (X5).
 *
 * @extends ServiceEntityRepository<ExchangeRead>
 */
class ExchangeReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRead::class);
    }

    public function findOneFor(Membership $membership, Exchange $exchange): ?ExchangeRead
    {
        return $this->findOneBy(['membership' => $membership, 'exchange' => $exchange]);
    }

    /**
     * Toutes les lectures d'un membre pour sa famille, indexées par identifiant d'échange.
     *
     * Une requête pour la liste entière : marquer les fils non lus ne doit pas coûter un aller-retour
     * par carte (§3.5, même règle que les décomptes de votes et de messages).
     *
     * @return array<string, array{at: \DateTimeImmutable, messageId: ?string}>
     */
    public function lastReadByExchange(Membership $membership, Family $family): array
    {
        /** @var list<ExchangeRead> $rows */
        $rows = $this->createQueryBuilder('r')
            ->join('r.exchange', 'e')
            ->andWhere('r.membership = :membership')
            ->andWhere('e.family = :family')
            ->setParameter('membership', $membership)
            ->setParameter('family', $family)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->getExchange()->getId()] = [
                'at' => $row->getLastReadAt(),
                'messageId' => null !== $row->getLastReadMessage() ? (string) $row->getLastReadMessage()->getId() : null,
            ];
        }

        return $map;
    }

    /**
     * Upsert idempotent : la lecture n'avance jamais en arrière — ni dans le temps, ni dans le fil
     * (un marquage tardif arrivé après un plus récent ne doit pas rendre l'échange non lu).
     */
    public function markRead(Membership $membership, Exchange $exchange, \DateTimeImmutable $at, ?Message $lastMessage = null): ExchangeRead
    {
        $read = $this->findOneFor($membership, $exchange);
        if (null === $read) {
            $read = new ExchangeRead($membership, $exchange, $at);
            $read->setLastReadMessage($lastMessage);
            $this->getEntityManager()->persist($read);

            return $read;
        }
        if ($read->getLastReadAt() > $at) {
            return $read;
        }
        $read->setLastReadAt($at);
        $known = $read->getLastReadMessage();
        if (null === $known || (null !== $lastMessage && $lastMessage->getId()->toRfc4122() > $known->getId()->toRfc4122())) {
            $read->setLastReadMessage($lastMessage);
        }

        return $read;
    }
}
