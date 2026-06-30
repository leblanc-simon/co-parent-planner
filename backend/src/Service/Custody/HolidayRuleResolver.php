<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Enum\HolidayRuleKind;

/**
 * Résout l'attribution slot (`A`/`B`) jour par jour d'une période de vacances selon sa règle
 * (Lot 3 §3.3). Logique **pure** (sans BD). `eve_alternating` est ancré sur l'**année civile**
 * du réveillon (déterminisme stable d'une régénération à l'autre).
 */
final class HolidayRuleResolver
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string> 'Y-m-d' → 'A'|'B'
     */
    public function assignments(HolidayRuleKind $kind, array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->assignKind($kind, $params, $start, $end);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function assignKind(HolidayRuleKind $kind, array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return match ($kind) {
            HolidayRuleKind::FixedParent => $this->fixedParent($params, $start, $end),
            HolidayRuleKind::Split5050 => $this->split($params, $start, $end),
            HolidayRuleKind::AlternatingWeeks => $this->alternatingWeeks($params, $start, $end),
            HolidayRuleKind::EveAlternating => $this->eveAlternating($params, $start, $end),
            HolidayRuleKind::Custom => $this->custom($params, $start, $end),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function fixedParent(array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $parent = self::slot($params['parent'] ?? 'A');
        $map = [];
        foreach ($this->eachDay($start, $end) as $d) {
            $map[$d] = $parent;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function split(array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $first = self::slot($params['firstHalf'] ?? 'A');
        $second = self::slot($params['secondHalf'] ?? 'B');
        $total = CustodyDateMath::daysBetween($start, $end) + 1;
        $half = intdiv($total, 2);
        $map = [];
        $i = 0;
        foreach ($this->eachDay($start, $end) as $d) {
            $map[$d] = $i < $half ? $first : $second;
            ++$i;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function alternatingWeeks(array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $startParent = self::slot($params['startParent'] ?? 'A');
        $map = [];
        $startDay = $start->setTime(0, 0);
        foreach ($this->eachDay($start, $end) as $d) {
            $k = intdiv(CustodyDateMath::daysBetween($startDay, new \DateTimeImmutable($d)), 7);
            $map[$d] = 0 === $k % 2 ? $startParent : BasePatternResolver::other($startParent);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function eveAlternating(array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $evenParent = self::slot($params['evenParent'] ?? 'A');
        /** @var list<array{month:int, day:int}> $eves */
        $eves = $params['eves'] ?? [['month' => 12, 'day' => 24], ['month' => 12, 'day' => 31]];
        /** @var array{kind?: string, params?: array<string,mixed>} $baseRuleRaw */
        $baseRuleRaw = $params['baseRule'] ?? ['kind' => 'split_50_50', 'params' => ['firstHalf' => 'A', 'secondHalf' => 'B']];
        $baseKind = HolidayRuleKind::tryFrom($baseRuleRaw['kind'] ?? 'split_50_50') ?? HolidayRuleKind::Split5050;

        // Jours non-réveillon : règle de base sur toute la période.
        $map = $this->assignKind($baseKind, $baseRuleRaw['params'] ?? [], $start, $end);

        // Réveillons : alternance ancrée sur l'année civile.
        foreach ($this->eachDay($start, $end) as $d) {
            $date = new \DateTimeImmutable($d);
            if ($this->isEve($date, $eves)) {
                $even = 0 === ((int) $date->format('Y')) % 2;
                $map[$d] = $even ? $evenParent : BasePatternResolver::other($evenParent);
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, string>
     */
    private function custom(array $params, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        /** @var array<string, string> $assignments */
        $assignments = $params['assignments'] ?? [];
        $map = [];
        foreach ($this->eachDay($start, $end) as $d) {
            if (isset($assignments[$d])) {
                $map[$d] = self::slot($assignments[$d]);
            }
        }

        return $map;
    }

    /**
     * @param list<array{month:int, day:int}> $eves
     */
    private function isEve(\DateTimeImmutable $date, array $eves): bool
    {
        $m = (int) $date->format('n');
        $day = (int) $date->format('j');
        foreach ($eves as $eve) {
            if ((int) ($eve['month'] ?? 0) === $m && (int) ($eve['day'] ?? 0) === $day) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Generator<string>
     */
    private function eachDay(\DateTimeImmutable $start, \DateTimeImmutable $end): \Generator
    {
        $cursor = $start->setTime(0, 0);
        $last = $end->setTime(0, 0);
        while ($cursor <= $last) {
            yield $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }
    }

    /** @return 'A'|'B' */
    private static function slot(mixed $value): string
    {
        return 'B' === $value ? 'B' : 'A';
    }
}
