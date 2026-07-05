<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exchange;
use App\Entity\ExchangeOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExchangeOption>
 */
class ExchangeOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeOption::class);
    }

    /** @return list<ExchangeOption> options d'un échange, ordonnées par position. */
    public function findForExchange(Exchange $exchange): array
    {
        /** @var list<ExchangeOption> $result */
        $result = $this->createQueryBuilder('o')
            ->andWhere('o.exchange = :exchange')->setParameter('exchange', $exchange)
            ->orderBy('o.position', 'ASC')
            ->getQuery()->getResult();

        return $result;
    }
}
