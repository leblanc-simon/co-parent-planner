<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Événements **ponctuels** (`seriesId IS NULL`) chevauchant la fenêtre [from, to].
     * Chevauchement : `startsAt <= to` ET `COALESCE(endsAt, startsAt) >= from` — capte un événement
     * qui déborde dans la fenêtre (Lot 4 §4.11). Tri déterministe (starts, id).
     *
     * @return list<Event>
     */
    public function findPointEventsOverlapping(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<Event> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->andWhere('e.seriesId IS NULL')
            ->andWhere('e.startsAt <= :to')
            ->andWhere('COALESCE(e.endsAt, e.startsAt) >= :from')
            ->setParameter('family', $family)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Toutes les lignes rattachées à une série (`seriesId IS NOT NULL`) : maîtres, overrides et
     * EXDATE de la famille. On borne les **maîtres** à `startsAt <= to` (une série ne peut produire
     * d'occurrence avant son ancre) sans filtrer les overrides/EXDATE (dont la date peut différer).
     * Le regroupement par série et l'expansion sont faits par l'appelant (§3.2/§3.3).
     *
     * @return list<Event>
     */
    public function findSeriesRowsWithin(Family $family, \DateTimeImmutable $to): array
    {
        /** @var list<Event> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->andWhere('e.seriesId IS NOT NULL')
            ->andWhere('e.isOverride = true OR e.cancelled = true OR e.startsAt <= :to')
            ->setParameter('family', $family)
            ->setParameter('to', $to)
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Événement déjà créé pour une clé d'idempotence donnée dans la famille (rejeu d'outbox §4.3). */
    public function findOneByIdempotencyKey(Family $family, string $key): ?Event
    {
        return $this->findOneBy(['family' => $family, 'idempotencyKey' => $key]);
    }

    /**
     * Toutes les lignes d'une série donnée (maître + overrides + EXDATE), pour l'édition/suppression
     * par portée et la scission (§3.5). Triées par date pour un traitement déterministe.
     *
     * @return list<Event>
     */
    public function findBySeriesId(Uuid $seriesId): array
    {
        /** @var list<Event> $result */
        $result = $this->createQueryBuilder('e')
            ->andWhere('e.seriesId = :seriesId')
            ->setParameter('seriesId', $seriesId)
            ->orderBy('e.startsAt', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
