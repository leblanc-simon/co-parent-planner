<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Family;
use App\Entity\SchoolHoliday;
use App\Enum\HolidaySource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolHoliday>
 */
class SchoolHolidayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolHoliday::class);
    }

    /**
     * Périodes **officielles** existantes d'une année scolaire, pour le balayage idempotent de
     * l'import (suppression des périodes disparues / rectifiées côté source). Une famille n'importe
     * qu'une zone/académie par an : scoper sur (famille, année) suffit et évite les mismatches de
     * casse sur l'académie stockée (location).
     *
     * @return list<SchoolHoliday>
     */
    public function findOfficialBySchoolYear(Family $family, ?string $schoolYear): array
    {
        /** @var list<SchoolHoliday> $result */
        $result = $this->createQueryBuilder('h')
            ->andWhere('h.family = :family')
            ->andWhere('h.source = :source')
            ->andWhere('h.schoolYear = :schoolYear')
            ->setParameter('family', $family)
            ->setParameter('source', HolidaySource::Official->value)
            ->setParameter('schoolYear', $schoolYear)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** @return list<SchoolHoliday> */
    public function findByFamily(Family $family): array
    {
        /** @var list<SchoolHoliday> $result */
        $result = $this->createQueryBuilder('h')
            ->andWhere('h.family = :family')
            ->setParameter('family', $family)
            ->orderBy('h.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * **Toutes** les périodes (avec ou sans règle) chevauchant [from, to], triées (start, end, id).
     * Utilisé par l'agenda pour l'exclusion des occurrences tombant en vacances (Lot 4 §3.8) —
     * indépendamment de toute règle de garde.
     *
     * @return list<SchoolHoliday>
     */
    public function findOverlapping(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<SchoolHoliday> $result */
        $result = $this->createQueryBuilder('h')
            ->andWhere('h.family = :family')
            ->andWhere('h.startDate <= :to')
            ->andWhere('h.endDate >= :from')
            ->setParameter('family', $family)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('h.startDate', 'ASC')
            ->addOrderBy('h.endDate', 'ASC')
            ->addOrderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Périodes **avec règle** chevauchant [from, to], triées (start, end, id) pour un ordre
     * d'application déterministe (§3.3 : la dernière écrite gagne sur un jour partagé).
     *
     * @return list<SchoolHoliday>
     */
    public function findWithRuleOverlapping(Family $family, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<SchoolHoliday> $result */
        $result = $this->createQueryBuilder('h')
            ->innerJoin('h.rule', 'r')
            ->addSelect('r')
            ->andWhere('h.family = :family')
            ->andWhere('h.startDate <= :to')
            ->andWhere('h.endDate >= :from')
            ->setParameter('family', $family)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->orderBy('h.startDate', 'ASC')
            ->addOrderBy('h.endDate', 'ASC')
            ->addOrderBy('h.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /** Clé naturelle d'upsert d'import (§3.10). */
    public function findOneByNaturalKey(
        Family $family,
        ?string $zone,
        ?string $academy,
        ?string $schoolYear,
        string $label,
        \DateTimeImmutable $startDate,
    ): ?SchoolHoliday {
        return $this->findOneBy([
            'family' => $family,
            'zone' => $zone,
            'academy' => $academy,
            'schoolYear' => $schoolYear,
            'label' => $label,
            'startDate' => $startDate,
        ]);
    }
}
