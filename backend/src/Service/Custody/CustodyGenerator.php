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
        $from = max($this->cal($pattern->getAnchorDate()), $today);
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
        $from = max($this->cal($exception->getStartDate()), $this->today());
        $to = $this->cal($exception->getEndDate());
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
        $from = max($this->cal($start), $this->today());
        $to = $this->cal($end);
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
            //    Le découpage de la règle (moitiés, semaines, réveillons) est calculé sur la période
            //    COMPLÈTE de la vacance — jamais clampée à la fenêtre — pour rester identique quelle
            //    que soit la fenêtre régénérée (idempotence) ; on ne matérialise que [from, to].
            $fromStr = $from->format('Y-m-d');
            $toStr = $to->format('Y-m-d');
            foreach ($this->holidays->findWithRuleOverlapping($family, $from, $to) as $holiday) {
                $rule = $holiday->getRule();
                if (null === $rule) {
                    continue;
                }
                $assignments = $this->rules->assignments(
                    $rule->getKind(),
                    $rule->getParams(),
                    $this->cal($holiday->getStartDate()),
                    $this->cal($holiday->getEndDate()),
                );
                foreach ($assignments as $dateStr => $slot) {
                    if ($dateStr < $fromStr || $dateStr > $toStr) {
                        continue;
                    }
                    $this->upsertHoliday($family, $this->cal(new \DateTimeImmutable($dateStr)), 'A' === $slot ? $aId : $bId);
                }
            }

            // 4. Ré-application des exceptions restantes chevauchant la fenêtre (priorité max).
            foreach ($this->exceptions->findOverlapping($family, $from, $to) as $exception) {
                $eFrom = max($this->cal($exception->getStartDate()), $from);
                $eTo = min($this->cal($exception->getEndDate()), $to);
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
     * Renormalise une date d'entité (hydratée à minuit UTC par Doctrine, faute de `date.timezone`)
     * en minuit Europe/Paris du même jour calendaire — pour la comparer sans off-by-one à `today()`
     * et itérer en jours calendaires (cf. invariant « comparer des dates Y-m-d, jamais des instants »).
     */
    private function cal(\DateTimeImmutable $d): \DateTimeImmutable
    {
        $r = \DateTimeImmutable::createFromFormat('!Y-m-d', $d->format('Y-m-d'), new \DateTimeZone('Europe/Paris'));
        \assert(false !== $r);

        return $r;
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
