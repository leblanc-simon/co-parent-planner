<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Enum\RecurrenceFreq;

/**
 * Expansion **pure** (sans BD) d'une série sur une fenêtre [from, to] (cdc §2.5 ; Lot 4 §3.2).
 *
 * Principes verrouillés :
 *  - chaque occurrence est calculée **depuis l'ancre** (non cumulatif) → l'occurrence N est stable
 *    quelle que soit la fenêtre interrogée (§3.2.1) ;
 *  - l'**heure murale** de l'ancre est reconstruite à chaque occurrence dans le fuseau famille →
 *    stable à travers les changements d'heure (DST). Heure inexistante (gap de printemps) → décalage
 *    en avant ; heure ambiguë (recul d'automne) → première occurrence (comportement natif PHP) ;
 *  - `monthly` conserve le quantième, **clamp** au dernier jour du mois si absent (jamais de saut) ;
 *  - `until` est **inclusif** (comparé en date locale) ; `count` compte l'ancre comme 1 ;
 *  - borne anti-explosion défensive `MAX_OCCURRENCES_PER_SERIES` (l'`EventWriter` garantit en amont
 *    qu'aucune série valide ne l'atteint).
 */
final class OccurrenceExpander
{
    /** Garde-fou : nombre maximal d'occurrences générées pour une série (§4.2). */
    public const int MAX_OCCURRENCES_PER_SERIES = 730;

    /**
     * @return list<OccurrenceInstant> occurrences dont l'instant de début ∈ [from, to] (bornes incluses)
     */
    public function expand(
        RecurrenceRule $rule,
        \DateTimeImmutable $anchorStart,
        ?\DateTimeImmutable $anchorEnd,
        \DateTimeZone $tz,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $utc = new \DateTimeZone('UTC');
        $durationSeconds = null !== $anchorEnd
            ? $anchorEnd->getTimestamp() - $anchorStart->getTimestamp()
            : null;

        $anchorLocal = $anchorStart->setTimezone($tz);
        $anchorTime = $anchorLocal->format('H:i:s');
        $anchorDay = (int) $anchorLocal->format('j');
        $anchorMidnight = $anchorLocal->setTime(0, 0);

        $freq = $rule->freq;
        $untilKey = $rule->until?->format('Y-m-d');

        $out = [];
        for ($n = 0;; ++$n) {
            if (null !== $rule->count && $n >= $rule->count) {
                break;
            }
            if ($n >= self::MAX_OCCURRENCES_PER_SERIES) {
                break;
            }

            $localDate = $this->advanceDate($anchorMidnight, $anchorDay, $freq, $n, $tz);
            $localKey = $localDate->format('Y-m-d');
            if (null !== $untilKey && $localKey > $untilKey) {
                break;
            }

            $startLocal = $this->applyTime($localDate, $anchorTime);
            $startUtc = $startLocal->setTimezone($utc);
            if ($startUtc > $to) {
                break; // occurrences croissantes : plus rien d'utile
            }
            if ($startUtc >= $from) {
                $endUtc = null !== $durationSeconds
                    ? $startUtc->modify(\sprintf('+%d seconds', $durationSeconds))
                    : null;
                $out[] = new OccurrenceInstant($startUtc, $endUtc, $localDate);
            }

            if (!$freq->isRecurring()) {
                break; // événement ponctuel : une seule itération
            }
        }

        return $out;
    }

    /**
     * Occurrence virtuelle d'une série tombant exactement sur `localDate` (date d'origine), ou null
     * si `localDate` n'est pas une occurrence valide de la série. Sert à valider une cible d'override
     * / EXDATE et à en déduire le créneau d'origine (§3.4/§3.5).
     */
    public function occurrenceOn(
        RecurrenceRule $rule,
        \DateTimeImmutable $anchorStart,
        ?\DateTimeImmutable $anchorEnd,
        \DateTimeZone $tz,
        \DateTimeImmutable $localDate,
    ): ?OccurrenceInstant {
        $utc = new \DateTimeZone('UTC');
        $dateStr = $localDate->format('Y-m-d');
        $dayStart = (new \DateTimeImmutable($dateStr.' 00:00:00', $tz))->setTimezone($utc);
        $dayEnd = (new \DateTimeImmutable($dateStr.' 23:59:59', $tz))->setTimezone($utc);

        foreach ($this->expand($rule, $anchorStart, $anchorEnd, $tz, $dayStart, $dayEnd) as $instant) {
            if ($instant->dateKey() === $dateStr) {
                return $instant;
            }
        }

        return null;
    }

    /**
     * Date locale (minuit fuseau famille) de l'occurrence `n`, calculée depuis l'ancre.
     * `weekly`/`biweekly` : +N jours ; `monthly` : +N mois en conservant le quantième (clamp).
     */
    private function advanceDate(
        \DateTimeImmutable $anchorMidnight,
        int $anchorDay,
        RecurrenceFreq $freq,
        int $n,
        \DateTimeZone $tz,
    ): \DateTimeImmutable {
        $step = $freq->stepDays();
        if (null !== $step) {
            return $anchorMidnight->modify(\sprintf('+%d days', $step * $n));
        }

        if (RecurrenceFreq::Monthly === $freq) {
            $anchorYear = (int) $anchorMidnight->format('Y');
            $anchorMonth = (int) $anchorMidnight->format('n');
            $total = ($anchorMonth - 1) + $n;
            $targetYear = $anchorYear + intdiv($total, 12);
            $targetMonth = ($total % 12) + 1;

            $firstOfMonth = \DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                \sprintf('%04d-%02d-01', $targetYear, $targetMonth),
                $tz,
            );
            \assert(false !== $firstOfMonth);
            $daysInMonth = (int) $firstOfMonth->format('t');

            return $firstOfMonth->setDate($targetYear, $targetMonth, min($anchorDay, $daysInMonth));
        }

        return $anchorMidnight; // none
    }

    private function applyTime(\DateTimeImmutable $localMidnight, string $hms): \DateTimeImmutable
    {
        $parts = explode(':', $hms);

        return $localMidnight->setTime(
            (int) $parts[0],
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        );
    }
}
