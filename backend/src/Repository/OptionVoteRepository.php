<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\Membership;
use App\Entity\OptionVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OptionVote>
 */
class OptionVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OptionVote::class);
    }

    /** Vote actif d'un membre sur une décision (unicité `(exchange, membership)`). */
    public function findOneForMember(Exchange $exchange, Membership $membership): ?OptionVote
    {
        return $this->findOneBy(['exchange' => $exchange, 'membership' => $membership]);
    }

    /** @return list<OptionVote> */
    public function findForExchange(Exchange $exchange): array
    {
        /** @var list<OptionVote> $result */
        $result = $this->findBy(['exchange' => $exchange]);

        return $result;
    }

    /**
     * Décompte des validations par option (`option_id => count`), pour une liste d'échanges.
     *
     * @param list<Exchange> $exchanges
     *
     * @return array<string, int> clé = (string) option id
     */
    public function tallyFor(array $exchanges): array
    {
        if ([] === $exchanges) {
            return [];
        }

        /** @var list<array{oid: string, cnt: int}> $rows */
        $rows = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.option) AS oid', 'COUNT(v.id) AS cnt')
            ->andWhere('v.exchange IN (:exchanges)')->setParameter('exchanges', $exchanges)
            ->groupBy('v.option')
            ->getQuery()->getResult();

        $tally = [];
        foreach ($rows as $row) {
            $tally[$row['oid']] = (int) $row['cnt'];
        }

        return $tally;
    }
}
