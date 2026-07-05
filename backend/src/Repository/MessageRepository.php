<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return list<Message> fil ordonné (§3.6) : `sent_at ASC, id ASC` (départage déterministe). */
    public function findForExchange(Exchange $exchange): array
    {
        /** @var list<Message> $result */
        $result = $this->createQueryBuilder('m')
            ->andWhere('m.exchange = :exchange')->setParameter('exchange', $exchange)
            ->orderBy('m.sentAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()->getResult();

        return $result;
    }

    public function findOneByIdempotencyKey(Exchange $exchange, string $key): ?Message
    {
        return $this->findOneBy(['exchange' => $exchange, 'idempotencyKey' => $key]);
    }

    /**
     * Décompte + dernier horodatage par échange (évite le N+1 dans la liste).
     *
     * @param list<Exchange> $exchanges
     *
     * @return array<string, array{count: int, last: ?\DateTimeImmutable}>
     */
    public function statsFor(array $exchanges): array
    {
        if ([] === $exchanges) {
            return [];
        }

        /** @var list<array{eid: string, cnt: int, last: ?string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.exchange) AS eid', 'COUNT(m.id) AS cnt', 'MAX(m.sentAt) AS last')
            ->andWhere('m.exchange IN (:exchanges)')->setParameter('exchanges', $exchanges)
            ->groupBy('m.exchange')
            ->getQuery()->getResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['eid']] = [
                'count' => (int) $row['cnt'],
                'last' => null !== $row['last'] ? new \DateTimeImmutable($row['last']) : null,
            ];
        }

        return $stats;
    }
}
