<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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

    /**
     * Identifiant du **dernier message** de chaque échange — repère du marqueur de non-lu (X5).
     *
     * Une requête pour toute la liste, comme les décomptes. En SQL natif : les identifiants sont des
     * `Uuid::v7` (ordonnés dans le temps), or PostgreSQL 16 n'a pas d'agrégat `max(uuid)` — d'où le
     * `DISTINCT ON`, qui répond exactement à la question posée sans transtypage.
     *
     * @param list<Exchange> $exchanges
     *
     * @return array<string, string> identifiant d'échange => identifiant du dernier message
     */
    public function lastIdsFor(array $exchanges): array
    {
        if ([] === $exchanges) {
            return [];
        }

        /** @var list<array{exchange_id: string, id: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT ON (exchange_id) exchange_id, id FROM messages WHERE exchange_id IN (?) ORDER BY exchange_id, id DESC',
            [array_map(static fn (Exchange $e): string => (string) $e->getId(), $exchanges)],
            [ArrayParameterType::STRING],
        );

        $ids = [];
        foreach ($rows as $row) {
            $ids[$row['exchange_id']] = $row['id'];
        }

        return $ids;
    }

    /**
     * Dernier message d'un échange, ou `null` si le fil est vide.
     *
     * Trié par identifiant : les `Uuid::v7` sont ordonnés dans le temps, là où `sentAt` est stocké à
     * la seconde — deux messages de la même seconde s'y départagent (X5).
     */
    public function lastFor(Exchange $exchange): ?Message
    {
        /** @var ?Message $message */
        $message = $this->createQueryBuilder('m')
            ->andWhere('m.exchange = :exchange')->setParameter('exchange', $exchange)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return $message;
    }
}
