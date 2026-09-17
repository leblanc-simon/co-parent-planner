<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Enum\CustodyDaySource;

/**
 * Tranche d'une journée de garde : de `fromMinutes` (inclus) à `toMinutes` (exclu), minutes
 * écoulées depuis minuit local, chez un parent et pour une raison donnée (base/vacances/exception).
 *
 * Une journée en compte une (journée unie), deux (transmission au milieu) ou trois (échange
 * partiel encadré par le rythme : « B jusqu'à 8h, A jusqu'à 18h, B ensuite »).
 */
final class DaySegment
{
    /** Minutes d'une journée — 100 % de la case. */
    public const int MINUTES_PER_DAY = 1440;

    public function __construct(
        public readonly int $fromMinutes,
        public readonly int $toMinutes,
        /** Membership du parent, `null` si le jour n'est pas attribué (membre anonymisé, §4.5). */
        public readonly ?string $parentId,
        public readonly CustodyDaySource $source,
    ) {
    }

    public function minutes(): int
    {
        return $this->toMinutes - $this->fromMinutes;
    }

    /** Part de la journée, en pourcentage (1 décimale) — l'unité dans laquelle la case est peinte. */
    public function percent(): float
    {
        return round($this->minutes() * 100 / self::MINUTES_PER_DAY, 1);
    }

    public function fromTime(): string
    {
        return self::toTimeString($this->fromMinutes);
    }

    /** Fin de tranche ; minuit se dit `24:00` — une fin à `00:00` se lirait comme un début. */
    public function toTime(): string
    {
        return self::toTimeString($this->toMinutes);
    }

    /** Minutes depuis minuit d'un `HH:MM`, ou `null` si la chaîne n'en est pas un. */
    public static function minutesOf(?string $time): ?int
    {
        if (null === $time || 1 !== preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours > 24 || $minutes > 59 || ($hours * 60 + $minutes) > self::MINUTES_PER_DAY) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    private static function toTimeString(int $minutes): string
    {
        return \sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
