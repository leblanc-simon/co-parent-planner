<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Agenda;

use App\Enum\RecurrenceFreq;
use App\Service\Agenda\OccurrenceExpander;
use App\Service\Agenda\OccurrenceInstant;
use App\Service\Agenda\RecurrenceRule;
use PHPUnit\Framework\TestCase;

/**
 * Cœur pur de l'expansion des séries (Lot 4 §3.2/§3.2.1) : cadence, clamp mensuel, `until` inclusif,
 * `count`, stabilité de l'heure murale à travers les changements d'heure (DST), fenêtrage.
 */
final class OccurrenceExpanderTest extends TestCase
{
    private OccurrenceExpander $expander;
    private \DateTimeZone $paris;

    protected function setUp(): void
    {
        $this->expander = new OccurrenceExpander();
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    public function testWeeklyByCount(): void
    {
        // Mardi 23 juin 2026, 17:30 Paris.
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 4);
        $out = $this->expand($rule, '2026-06-23 17:30');

        self::assertSame(
            ['2026-06-23 17:30', '2026-06-30 17:30', '2026-07-07 17:30', '2026-07-14 17:30'],
            $this->local($out),
        );
    }

    public function testBiweeklyByCount(): void
    {
        $rule = new RecurrenceRule(RecurrenceFreq::Biweekly, count: 3);
        $out = $this->expand($rule, '2026-06-23 17:30');

        self::assertSame(
            ['2026-06-23 17:30', '2026-07-07 17:30', '2026-07-21 17:30'],
            $this->local($out),
        );
    }

    public function testCountOneYieldsOnlyAnchor(): void
    {
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 1);
        self::assertSame(['2026-06-23 17:30'], $this->local($this->expand($rule, '2026-06-23 17:30')));
    }

    public function testUntilIsInclusive(): void
    {
        // until = date de la 3e occurrence → exactement 3 occurrences.
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, until: new \DateTimeImmutable('2026-07-07'));
        self::assertSame(
            ['2026-06-23 17:30', '2026-06-30 17:30', '2026-07-07 17:30'],
            $this->local($this->expand($rule, '2026-06-23 17:30')),
        );
    }

    public function testMonthlyClampsShortMonthsAndKeepsQuantieme(): void
    {
        // Ancre 31 janvier : févr. (28 en 2026) clampé, puis mars revient au 31 (calcul depuis l'ancre).
        $rule = new RecurrenceRule(RecurrenceFreq::Monthly, count: 4);
        self::assertSame(
            ['2026-01-31 09:00', '2026-02-28 09:00', '2026-03-31 09:00', '2026-04-30 09:00'],
            $this->local($this->expand($rule, '2026-01-31 09:00')),
        );
    }

    public function testMonthlyLeapFebruary(): void
    {
        // 2028 est bissextile → 29 février.
        $rule = new RecurrenceRule(RecurrenceFreq::Monthly, count: 2);
        self::assertSame(
            ['2028-01-31 09:00', '2028-02-29 09:00'],
            $this->local($this->expand($rule, '2028-01-31 09:00')),
        );
    }

    public function testWallClockStableAcrossSpringForward(): void
    {
        // Passage à l'heure d'été : dernier dimanche de mars 2026 = 29/03. 10:00 existe toujours.
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 3);
        $out = $this->expand($rule, '2026-03-22 10:00');

        // Heure murale stable à 10:00 des deux côtés de la transition.
        self::assertSame(['2026-03-22 10:00', '2026-03-29 10:00', '2026-04-05 10:00'], $this->local($out));
        // L'instant UTC se décale (+01:00 → +02:00) : 09:00 UTC avant, 08:00 UTC après.
        self::assertSame('2026-03-22 09:00', $out[0]->startsAt->format('Y-m-d H:i'));
        self::assertSame('2026-03-29 08:00', $out[1]->startsAt->format('Y-m-d H:i'));
    }

    public function testDstGapShiftsForward(): void
    {
        // 02:30 le 29/03/2026 n'existe pas (saut 02:00→03:00) → décalage en avant.
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 2);
        $out = $this->expand($rule, '2026-03-22 02:30');

        self::assertSame('2026-03-22 02:30', $this->local($out)[0]);
        self::assertSame('2026-03-29 03:30', $this->local($out)[1]);
    }

    public function testWindowSelectsMiddleOccurrencesStably(): void
    {
        // Occurrence N identique quelle que soit la fenêtre : on n'interroge que les occ. 3 à 5.
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 10);
        $from = $this->utc('2026-07-06 00:00');
        $to = $this->utc('2026-07-22 00:00');
        $out = $this->expander->expand($rule, $this->utc('2026-06-23 15:30'), null, $this->paris, $from, $to);

        self::assertSame(['2026-07-07 17:30', '2026-07-14 17:30', '2026-07-21 17:30'], $this->local($out));
    }

    public function testWindowEntirelyBeforeAnchorIsEmpty(): void
    {
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 5);
        $out = $this->expander->expand(
            $rule,
            $this->utc('2026-06-23 15:30'),
            null,
            $this->paris,
            $this->utc('2026-01-01 00:00'),
            $this->utc('2026-02-01 00:00'),
        );

        self::assertSame([], $out);
    }

    public function testDurationPreservedPerOccurrence(): void
    {
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 2);
        $out = $this->expander->expand(
            $rule,
            $this->utc('2026-06-23 15:30'),   // 17:30 Paris
            $this->utc('2026-06-23 16:30'),   // 18:30 Paris (durée 1 h)
            $this->paris,
            $this->utc('2026-06-01 00:00'),
            $this->utc('2026-07-31 00:00'),
        );

        self::assertCount(2, $out);
        foreach ($out as $occ) {
            self::assertNotNull($occ->endsAt);
            self::assertSame(3600, $occ->endsAt->getTimestamp() - $occ->startsAt->getTimestamp());
        }
        self::assertSame('2026-06-30 18:30', $out[1]->endsAt?->setTimezone($this->paris)->format('Y-m-d H:i'));
    }

    public function testNonRecurringYieldsSingleOccurrenceWhenInWindow(): void
    {
        $out = $this->expand(RecurrenceRule::none(), '2026-06-23 17:30');
        self::assertSame(['2026-06-23 17:30'], $this->local($out));
    }

    public function testRecurrenceDateKeyIsLocalOriginDate(): void
    {
        $rule = new RecurrenceRule(RecurrenceFreq::Weekly, count: 1);
        $out = $this->expand($rule, '2026-06-23 17:30');
        self::assertSame('2026-06-23', $out[0]->dateKey());
    }

    /**
     * Expansion sur une large fenêtre (couvre toutes les occurrences bornées par count/until).
     *
     * @return list<OccurrenceInstant>
     */
    private function expand(RecurrenceRule $rule, string $anchorParis): array
    {
        return $this->expander->expand(
            $rule,
            $this->parisToUtc($anchorParis),
            null,
            $this->paris,
            $this->utc('2020-01-01 00:00'),
            $this->utc('2035-01-01 00:00'),
        );
    }

    /**
     * @param list<OccurrenceInstant> $instants
     *
     * @return list<string> heures murales Paris (Y-m-d H:i)
     */
    private function local(array $instants): array
    {
        return array_map(
            fn (OccurrenceInstant $i): string => $i->startsAt->setTimezone($this->paris)->format('Y-m-d H:i'),
            $instants,
        );
    }

    private function parisToUtc(string $s): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($s, $this->paris))->setTimezone(new \DateTimeZone('UTC'));
    }

    private function utc(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
    }
}
