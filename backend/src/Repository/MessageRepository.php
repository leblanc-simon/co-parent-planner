<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\User;
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

    /** Rejeu scopé à l'**auteur** du message, jamais au seul échange (revue 2026-08 C10). */
    public function findOneByIdempotencyKey(Exchange $exchange, Membership $author, string $key): ?Message
    {
        return $this->findOneBy(['exchange' => $exchange, 'authorMembership' => $author, 'idempotencyKey' => $key]);
    }

    /**
     * Messages dont l'utilisateur est l'auteur (export RGPD, portabilité — §3.5).
     *
     * @return list<Message>
     */
    public function findByAuthorUser(User $user): array
    {
        /** @var list<Message> $result */
        $result = $this->createQueryBuilder('m')
            ->join('m.authorMembership', 'am')
            ->andWhere('am.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()->getResult();

        return $result;
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
