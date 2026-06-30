<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyPattern;
use App\Enum\CustodyPatternKind;

/**
 * Résout le **slot logique** (`A`/`B`) de la base de garde pour un jour donné, selon le modèle
 * (Lot 3 §3.2). Logique **pure** (sans BD), testable unitairement. Le mapping slot→membership
 * est fait par le générateur. Suppose `D >= anchor_date`.
 */
final class BasePatternResolver
{
    /** Motif canonique 2/2/3 figé sur 14 jours (indexé sur n % 14). */
    public const array MOTIF_223 = ['A', 'A', 'B', 'B', 'A', 'A', 'A', 'B', 'B', 'A', 'A', 'B', 'B', 'B'];

    /** @return 'A'|'B' */
    public function slotFor(CustodyPattern $pattern, \DateTimeImmutable $date): string
    {
        $anchor = $pattern->getAnchorDate();
        $n = CustodyDateMath::daysBetween($anchor, $date);

        return match ($pattern->getKind()) {
            CustodyPatternKind::Alternating => $this->alternating($pattern, $date),
            CustodyPatternKind::Pattern2223 => self::MOTIF_223[(($n % 14) + 14) % 14],
            CustodyPatternKind::Custom => $this->custom($pattern, $n),
            CustodyPatternKind::MidweekWeekends => $this->midweekWeekends($pattern, $date),
        };
    }

    /** @return 'A'|'B' */
    private function alternating(CustodyPattern $pattern, \DateTimeImmutable $date): string
    {
        $week = $this->weekIndex($pattern, $date);

        return 0 === $week % 2 ? 'A' : 'B';
    }

    /** @return 'A'|'B' */
    private function custom(CustodyPattern $pattern, int $n): string
    {
        $custom = $pattern->getCustomPattern() ?? [];
        $value = $custom[(($n % 14) + 14) % 14] ?? 'A';

        return 'B' === $value ? 'B' : 'A';
    }

    /** @return 'A'|'B' */
    private function midweekWeekends(CustodyPattern $pattern, \DateTimeImmutable $date): string
    {
        /** @var array{weekday_parents?: array<string,string>, weekend_days?: list<int>, weekend_start_parent?: string} $s */
        $s = $pattern->getSettings() ?? [];
        $weekendDays = array_map('intval', $s['weekend_days'] ?? []);
        $day = CustodyDateMath::weekday($date);

        if (\in_array($day, $weekendDays, true)) {
            $week = $this->weekIndex($pattern, $date);
            $base = 'A' === ($s['weekend_start_parent'] ?? 'A') ? 'A' : 'B';

            return 0 === $week % 2 ? $base : self::other($base);
        }

        $map = $s['weekday_parents'] ?? [];

        return 'B' === ($map[(string) $day] ?? 'A') ? 'B' : 'A';
    }

    /** Index de semaine (0-based) depuis le début de cycle aligné sur le jour d'échange. */
    private function weekIndex(CustodyPattern $pattern, \DateTimeImmutable $date): int
    {
        $start = CustodyDateMath::cycleStart($pattern->getAnchorDate(), $pattern->getHandoverWeekday());

        return intdiv(CustodyDateMath::daysBetween($start, $date), 7);
    }

    /** @return 'A'|'B' */
    public static function other(string $slot): string
    {
        return 'A' === $slot ? 'B' : 'A';
    }
}
