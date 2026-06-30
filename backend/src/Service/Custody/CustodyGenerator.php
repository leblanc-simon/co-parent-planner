<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyException;
use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Enum\CustodyDaySource;
use App\Repository\CustodyExceptionRepository;
use App\Repository\CustodyPatternRepository;
use App\Repository\SchoolHolidayRepository;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Moteur de matérialisation de la garde (Lot 3 §3.1) : pipeline **base → vacances → exceptions**,
 * **idempotent** (upsert sur `(family_id,date)`), **passé figé** (`date >= aujourd'hui`), priorité
 * **exception > holiday > base** garantie par l'ordre + des gardes SQL. Sérialisé par advisory lock.
 */
final class CustodyGenerator
{
    private const int HORIZON_MONTHS = 18;

    public function __construct(
        private readonly Connection $db,
        private readonly CustodyPatternRepository $patterns,
        private readonly SchoolHolidayRepository $holidays,
        private readonly CustodyExceptionRepository $exceptions,
        private readonly BasePatternResolver $base,
        private readonly HolidayRuleResolver $rules,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Régénère l'horizon glissant complet (≈18 mois) de la famille. Idempotent. */
    public function generate(Family $family): void
    {
        $pattern = $this->patterns->findOneByFamily($family);
        if (null === $pattern) {
            return; // rien à générer sans rythme
        }

        $today = $this->today();
        $from = max($pattern->getAnchorDate()->setTime(0, 0), $today);
        $to = $today->modify('+'.self::HORIZON_MONTHS.' months');
        if ($from > $to) {
            return;
        }

        $this->rebuildWindow($family, $pattern, $from, $to, clearExceptions: false);
    }

    /** Crée/écrase les jours d'une exception (priorité maximale). Clampé au présent. */
    public function applyException(CustodyException $exception): void
    {
        $family = $exception->getFamily();
        $from = max($exception->getStartDate()->setTime(0, 0), $this->today());
        $to = $exception->getEndDate()->setTime(0, 0);
        if ($from > $to) {
            return; // exception entièrement passée → rien à matérialiser
        }
        $parentId = $exception->getParentMembership()?->getId();

        $this->db->transactional(function () use ($family, $from, $to, $parentId): void {
            $this->lock($family);
            foreach ($this->eachDay($from, $to) as $date) {
                $this->upsertException($family, $date, $parentId);
            }
        });
    }

    /** Recalcule une fenêtre après suppression d'une exception (jours rendus à vacances/base). */
    public function recomputeWindow(Family $family, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $pattern = $this->patterns->findOneByFamily($family);
        $from = max($start->setTime(0, 0), $this->today());
        $to = $end->setTime(0, 0);
        if (null === $pattern || $from > $to) {
            return;
        }
        $this->rebuildWindow($family, $pattern, $from, $to, clearExceptions: true);
    }

    /**
     * Reconstruit une fenêtre : (option) purge, base (DO NOTHING), vacances (écrase base/holiday),
     * puis ré-applique les exceptions restantes. Le tout sous verrou, en transaction.
     */
    private function rebuildWindow(Family $family, CustodyPattern $pattern, \DateTimeImmutable $from, \DateTimeImmutable $to, bool $clearExceptions): void
    {
        $this->db->transactional(function () use ($family, $pattern, $from, $to, $clearExceptions): void {
            $this->lock($family);
            $fid = (string) $family->getId();
            $aId = $pattern->getParentA()?->getId();
            $bId = $pattern->getParentB()?->getId();

            // 1. Purge de la fenêtre : non-exception toujours ; exceptions seulement si demandé.
            $sql = 'DELETE FROM custody_days WHERE family_id = :fid AND date >= :from AND date <= :to';
            if (!$clearExceptions) {
                $sql .= " AND source <> 'exception'";
            }
            $this->db->executeStatement($sql, ['fid' => $fid, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

            // 2. Base : un jour par date, sans écraser une exception résiduelle (DO NOTHING).
            foreach ($this->eachDay($from, $to) as $date) {
                $slot = $this->base->slotFor($pattern, $date);
                $this->upsertBase($family, $date, 'A' === $slot ? $aId : $bId);
            }

            // 3. Vacances : écrase base/holiday (dernière période écrite gagne), jamais une exception.
            foreach ($this->holidays->findWithRuleOverlapping($family, $from, $to) as $holiday) {
                $rule = $holiday->getRule();
                if (null === $rule) {
                    continue;
                }
                $hStart = max($holiday->getStartDate()->setTime(0, 0), $from);
                $hEnd = min($holiday->getEndDate()->setTime(0, 0), $to);
                $assignments = $this->rules->assignments($rule->getKind(), $rule->getParams(), $hStart, $hEnd);
                foreach ($assignments as $dateStr => $slot) {
                    $this->upsertHoliday($family, new \DateTimeImmutable($dateStr), 'A' === $slot ? $aId : $bId);
                }
            }

            // 4. Ré-application des exceptions restantes chevauchant la fenêtre (priorité max).
            foreach ($this->exceptions->findOverlapping($family, $from, $to) as $exception) {
                $eFrom = max($exception->getStartDate()->setTime(0, 0), $from);
                $eTo = min($exception->getEndDate()->setTime(0, 0), $to);
                $parentId = $exception->getParentMembership()?->getId();
                foreach ($this->eachDay($eFrom, $eTo) as $date) {
                    $this->upsertException($family, $date, $parentId);
                }
            }
        });
    }

    private function upsertBase(Family $family, \DateTimeImmutable $date, ?Uuid $parentId): void
    {
        // N'insère que si la date est libre : préserve une exception résiduelle (non purgée).
        $this->db->executeStatement(
            'INSERT INTO custody_days (id, family_id, date, parent_membership_id, source, created_at, updated_at)
             VALUES (:id, :fid, :date, :pid, :src, :now, :now)
             ON CONFLICT (family_id, date) DO NOTHING',
            $this->params($family, $date, $parentId, CustodyDaySource::Base),
        );
    }

    private function upsertHoliday(Family $family, \DateTimeImmutable $date, ?Uuid $parentId): void
    {
        $this->db->executeStatement(
            "INSERT INTO custody_days (id, family_id, date, parent_membership_id, source, created_at, updated_at)
             VALUES (:id, :fid, :date, :pid, :src, :now, :now)
             ON CONFLICT (family_id, date) DO UPDATE
               SET parent_membership_id = EXCLUDED.parent_membership_id, source = EXCLUDED.source, updated_at = EXCLUDED.updated_at
             WHERE custody_days.source <> 'exception'",
            $this->params($family, $date, $parentId, CustodyDaySource::Holiday),
        );
    }

    private function upsertException(Family $family, \DateTimeImmutable $date, ?Uuid $parentId): void
    {
        $this->db->executeStatement(
            'INSERT INTO custody_days (id, family_id, date, parent_membership_id, source, created_at, updated_at)
             VALUES (:id, :fid, :date, :pid, :src, :now, :now)
             ON CONFLICT (family_id, date) DO UPDATE
               SET parent_membership_id = EXCLUDED.parent_membership_id, source = EXCLUDED.source, updated_at = EXCLUDED.updated_at',
            $this->params($family, $date, $parentId, CustodyDaySource::Exception),
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function params(Family $family, \DateTimeImmutable $date, ?Uuid $parentId, CustodyDaySource $source): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'fid' => (string) $family->getId(),
            'date' => $date->format('Y-m-d'),
            'pid' => null !== $parentId ? (string) $parentId : null,
            'src' => $source->value,
            'now' => $this->clock->now()->format('Y-m-d H:i:s'),
        ];
    }

    /** Advisory lock transactionnel par famille (sérialise les régénérations concurrentes). */
    private function lock(Family $family): void
    {
        $this->db->executeStatement('SELECT pg_advisory_xact_lock(hashtext(:k))', ['k' => 'custody:'.$family->getId()]);
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
    }

    /**
     * @return \Generator<\DateTimeImmutable>
     */
    private function eachDay(\DateTimeImmutable $from, \DateTimeImmutable $to): \Generator
    {
        $cursor = $from->setTime(0, 0);
        $last = $to->setTime(0, 0);
        while ($cursor <= $last) {
            yield $cursor;
            $cursor = $cursor->modify('+1 day');
        }
    }
}
