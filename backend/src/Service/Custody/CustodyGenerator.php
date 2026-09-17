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
 * Moteur de matérialisation de la garde (Lot 3 §3.1, cdc §3.4) : pipeline **base → vacances →
 * exceptions**, **idempotent** (upsert sur `(family_id,date)`), **passé figé**
 * (`date >= aujourd'hui`), priorité **exception > holiday > base**. Sérialisé par advisory lock.
 *
 * Chaque journée est calculée **entière** en PHP — son découpage horaire (`DaySegments`) puis le
 * parent majoritaire qui la porte — et écrite en une ligne. La priorité ne tient donc plus à
 * l'ordre de trois requêtes SQL et à leurs gardes, mais à l'ordre de superposition des tranches :
 * le nominal (rythme, puis règle de vacances), sur lequel les exceptions viennent s'appliquer à
 * l'heure près.
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

        $this->rebuildWindow($family, $pattern, $from, $to);
    }

    /** Recalcule les jours couverts par une exception (création ou remplacement). */
    public function applyException(CustodyException $exception): void
    {
        $this->recomputeWindow($exception->getFamily(), $exception->getStartDate(), $exception->getEndDate());
    }

    /**
     * Recalcule une fenêtre après un changement ponctuel (exception posée ou retirée, règle de
     * vacances modifiée) : les jours y sont rendus au nominal, exceptions restantes réappliquées.
     *
     * La **veille** entre dans la fenêtre : la fin d'une journée appartient au parent du lendemain,
     * donc changer un jour change la case du précédent.
     */
    public function recomputeWindow(Family $family, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $pattern = $this->patterns->findOneByFamily($family);
        $from = max($this->cal($start)->modify('-1 day'), $this->today());
        $to = $this->cal($end);
        if (null === $pattern || $from > $to) {
            return;
        }
        $this->rebuildWindow($family, $pattern, $from, $to);
    }

    /**
     * Reconstruit une fenêtre : nominal (rythme puis règles de vacances), découpage horaire de
     * chaque journée, superposition des exceptions, puis une ligne par jour. Sous verrou, en
     * transaction.
     */
    private function rebuildWindow(Family $family, CustodyPattern $pattern, \DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $this->db->transactional(function () use ($family, $pattern, $from, $to): void {
            $this->lock($family);
            $handover = DaySegment::minutesOf($pattern->getHandoverTime());
            // Le lendemain du dernier jour est calculé sans être écrit : il porte le parent à qui
            // revient la fin de la journée précédente.
            $nominal = $this->nominalDays($family, $pattern, $from, $to->modify('+1 day'));
            $exceptions = $this->exceptions->findOverlapping($family, $from, $to);

            foreach ($this->eachDay($from, $to) as $date) {
                $key = $date->format('Y-m-d');
                $day = $nominal[$key] ?? ['parent' => null, 'source' => CustodyDaySource::Base];
                $next = $nominal[$date->modify('+1 day')->format('Y-m-d')] ?? null;

                $segments = DaySegments::nominal(
                    $day['parent'],
                    $next['parent'] ?? null,
                    $handover,
                    $day['source'],
                    $next['source'] ?? CustodyDaySource::Base,
                );
                // Les exceptions s'appliquent à la lettre, sur leurs propres heures : elles ne
                // suivent pas la convention de transmission du rythme — c'est tout l'intérêt de
                // pouvoir dire « du 25 à 18:00 au 26 à 18:00 ».
                foreach ($exceptions as $exception) {
                    $window = $this->exceptionMinutes($exception, $key);
                    if (null === $window) {
                        continue;
                    }
                    $parentId = $exception->getParentMembership()?->getId();
                    $segments = $segments->overlay(
                        $window[0],
                        $window[1],
                        null !== $parentId ? (string) $parentId : null,
                        CustodyDaySource::Exception,
                    );
                }

                $this->upsertDay($family, $date, $segments->merged());
            }
        });
    }

    /**
     * Attribution **nominale** (rythme, puis règles de vacances) de chaque jour de la fenêtre.
     *
     * @return array<string, array{parent: ?string, source: CustodyDaySource}>
     */
    private function nominalDays(Family $family, CustodyPattern $pattern, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $aId = $pattern->getParentA()?->getId();
        $bId = $pattern->getParentB()?->getId();
        $parentOf = static fn (string $slot): ?string => null !== ('A' === $slot ? $aId : $bId)
            ? (string) ('A' === $slot ? $aId : $bId)
            : null;

        $nominal = [];
        foreach ($this->eachDay($from, $to) as $date) {
            $nominal[$date->format('Y-m-d')] = [
                'parent' => $parentOf($this->base->slotFor($pattern, $date)),
                'source' => CustodyDaySource::Base,
            ];
        }

        // Le découpage d'une règle de vacances (moitiés, semaines, réveillons) est calculé sur la
        // période COMPLÈTE — jamais clampée à la fenêtre — pour rester identique quelle que soit la
        // fenêtre régénérée (idempotence) ; seuls les jours de [from, to] sont retenus.
        $fromKey = $from->format('Y-m-d');
        $toKey = $to->format('Y-m-d');
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
                if ($dateStr < $fromKey || $dateStr > $toKey) {
                    continue;
                }
                $nominal[$dateStr] = ['parent' => $parentOf($slot), 'source' => CustodyDaySource::Holiday];
            }
        }

        return $nominal;
    }

    /**
     * Part de la journée `$dayKey` couverte par l'exception, en minutes `[début, fin)`, ou `null`
     * si elle ne la touche pas.
     *
     * Heures absentes = journées entières : c'est la forme des exceptions posées avant que la garde
     * ne connaisse les heures, et celle du formulaire quand on n'en demande pas.
     *
     * @return array{0: int, 1: int}|null
     */
    private function exceptionMinutes(CustodyException $exception, string $dayKey): ?array
    {
        $startKey = $exception->getStartDate()->format('Y-m-d');
        $endKey = $exception->getEndDate()->format('Y-m-d');
        if ($dayKey < $startKey || $dayKey > $endKey) {
            return null;
        }

        $from = $dayKey === $startKey ? (DaySegment::minutesOf($exception->getStartTime()) ?? 0) : 0;
        $to = $dayKey === $endKey
            ? (DaySegment::minutesOf($exception->getEndTime()) ?? DaySegment::MINUTES_PER_DAY)
            : DaySegment::MINUTES_PER_DAY;

        return $from < $to ? [$from, $to] : null;
    }

    /**
     * Écrit la journée : le parent **majoritaire** la porte (c'est lui que comptent les
     * statistiques et qu'affiche la case), le découpage n'est stocké que s'il y a quelque chose à
     * découper — une journée unie n'a pas de tranches.
     */
    private function upsertDay(Family $family, \DateTimeImmutable $date, DaySegments $segments): void
    {
        $params = $this->params($family, $date, $segments->majorityParentId(), $segments->dominantSource());
        $params['segments'] = $segments->isUniform() ? null : json_encode($segments->toArray(), \JSON_THROW_ON_ERROR);

        $this->db->executeStatement(
            'INSERT INTO custody_days (id, family_id, date, parent_membership_id, source, segments, created_at, updated_at)
             VALUES (:id, :fid, :date, :pid, :src, CAST(:segments AS JSON), :now, :now)
             ON CONFLICT (family_id, date) DO UPDATE
               SET parent_membership_id = EXCLUDED.parent_membership_id, source = EXCLUDED.source,
                   segments = EXCLUDED.segments, updated_at = EXCLUDED.updated_at',
            $params,
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function params(Family $family, \DateTimeImmutable $date, ?string $parentId, CustodyDaySource $source): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'fid' => (string) $family->getId(),
            'date' => $date->format('Y-m-d'),
            'pid' => $parentId,
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
