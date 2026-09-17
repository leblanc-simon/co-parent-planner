<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Enum\CustodyDaySource;

/**
 * Découpage horaire d'une journée de garde (cdc §3.4) — la vérité que le moteur matérialise, et
 * dont la case du planning n'est que la peinture.
 *
 * **Convention de transmission** : la journée appartient au parent du jour **jusqu'à l'heure
 * d'échange**, puis à celui du lendemain. Autrement dit, la transmission se lit sur la **dernière**
 * case d'un bloc de garde — « A jusqu'au 25, B du 26 au 28, échange à 18:00 » met les enfants chez
 * A jusqu'au 25 à 18:00, et c'est la case du 25 qui porte les deux couleurs.
 *
 * Les **exceptions** ne suivent pas cette règle : elles portent des instants explicites
 * (`start_time`/`end_time`) et s'appliquent littéralement par-dessus le nominal. C'est ce qui
 * permet à « Échanger ce jour » de proposer « du 25 18:00 au 26 18:00 » plutôt que de deviner.
 */
final class DaySegments
{
    /** @param list<DaySegment> $segments couvre 0 → 1440 sans trou ni chevauchement */
    private function __construct(public readonly array $segments)
    {
    }

    /** Journée entière chez un seul parent. */
    public static function wholeDay(?string $parentId, CustodyDaySource $source): self
    {
        return new self([new DaySegment(0, DaySegment::MINUTES_PER_DAY, $parentId, $source)]);
    }

    /**
     * Journée **nominale** : le parent du jour jusqu'à l'heure d'échange, celui du lendemain
     * ensuite.
     *
     * Journée unie quand il n'y a rien à partager : même parent le lendemain, lendemain hors de
     * l'horizon généré (`$nextParentId` null — on ne devine pas), pas d'heure d'échange, ou échange
     * à minuit, qui donne bien la journée entière.
     */
    public static function nominal(
        ?string $parentId,
        ?string $nextParentId,
        ?int $handoverMinutes,
        CustodyDaySource $source,
        CustodyDaySource $nextSource,
    ): self {
        if (null === $nextParentId || $nextParentId === $parentId
            || null === $handoverMinutes || 0 === $handoverMinutes || $handoverMinutes >= DaySegment::MINUTES_PER_DAY) {
            return self::wholeDay($parentId, $source);
        }

        return new self([
            new DaySegment(0, $handoverMinutes, $parentId, $source),
            new DaySegment($handoverMinutes, DaySegment::MINUTES_PER_DAY, $nextParentId, $nextSource),
        ]);
    }

    /**
     * Superpose `[from, to)` chez `$parentId` — priorité maximale, comme une exception sur le
     * rythme. Une plage vide ou inversée ne change rien.
     */
    public function overlay(int $from, int $to, ?string $parentId, CustodyDaySource $source): self
    {
        $from = max(0, $from);
        $to = min(DaySegment::MINUTES_PER_DAY, $to);
        if ($from >= $to) {
            return $this;
        }

        $result = [];
        foreach ($this->segments as $segment) {
            // Part du segment antérieure à la superposition, puis part postérieure : ce qui reste
            // du rythme de part et d'autre de l'échange.
            if ($segment->fromMinutes < $from) {
                $result[] = new DaySegment($segment->fromMinutes, min($segment->toMinutes, $from), $segment->parentId, $segment->source);
            }
            if ($segment->toMinutes > $to) {
                $result[] = new DaySegment(max($segment->fromMinutes, $to), $segment->toMinutes, $segment->parentId, $segment->source);
            }
        }
        $result[] = new DaySegment($from, $to, $parentId, $source);
        usort($result, static fn (DaySegment $a, DaySegment $b) => $a->fromMinutes <=> $b->fromMinutes);

        return (new self($result))->merged();
    }

    /**
     * Fusionne les tranches voisines qui mènent au même endroit : **même parent**, quelle que soit
     * l'origine — un échange qui prolonge le rythme du même parent ne doit pas laisser un filet en
     * travers d'une journée où personne ne bouge. La tranche fusionnée garde l'origine la plus
     * prioritaire, pour que le jour reste signalé comme exceptionnel.
     */
    public function merged(): self
    {
        $merged = [];
        $current = null;
        foreach ($this->segments as $segment) {
            if (null !== $current && $current->parentId === $segment->parentId) {
                $source = self::rank($segment->source) > self::rank($current->source) ? $segment->source : $current->source;
                $current = new DaySegment($current->fromMinutes, $segment->toMinutes, $current->parentId, $source);
                continue;
            }
            if (null !== $current) {
                $merged[] = $current;
            }
            $current = $segment;
        }
        if (null !== $current) {
            $merged[] = $current;
        }

        return new self($merged);
    }

    public function isUniform(): bool
    {
        return 1 === \count($this->segments);
    }

    /**
     * Parent à qui la journée est **comptée** : celui qui y passe le plus d'heures (décision
     * produit du 2026-09-11). La case porte son initiale et les statistiques lui créditent le jour
     * entier. À égalité parfaite, celui qui a la nuit — c'est lui qui couche les enfants.
     */
    public function majorityParentId(): ?string
    {
        $minutesByParent = [];
        foreach ($this->segments as $segment) {
            $key = $segment->parentId ?? '';
            $minutesByParent[$key] = ($minutesByParent[$key] ?? 0) + $segment->minutes();
        }

        $best = null;
        $bestMinutes = -1;
        foreach ($minutesByParent as $key => $minutes) {
            if ($minutes > $bestMinutes) {
                $best = '' === $key ? null : $key;
                $bestMinutes = $minutes;
            }
        }

        $night = $this->segments[\count($this->segments) - 1]->parentId ?? null;
        $nightMinutes = $minutesByParent[$night ?? ''] ?? 0;

        return $nightMinutes === $bestMinutes ? $night : $best;
    }

    /**
     * Origine affichée pour la journée : la plus prioritaire présente. Un jour touché par une
     * exception, fût-ce deux heures, reste signalé comme tel sur le planning.
     */
    public function dominantSource(): CustodyDaySource
    {
        $best = CustodyDaySource::Base;
        foreach ($this->segments as $segment) {
            if (self::rank($segment->source) > self::rank($best)) {
                $best = $segment->source;
            }
        }

        return $best;
    }

    /** Priorité du moteur : exception > vacances > base. */
    private static function rank(CustodyDaySource $source): int
    {
        return match ($source) {
            CustodyDaySource::Base => 0,
            CustodyDaySource::Holiday => 1,
            CustodyDaySource::Exception => 2,
        };
    }

    /**
     * Forme stockée (colonne `custody_days.segments`) et exposée par l'API.
     *
     * @return list<array{from: string, to: string, parent: ?string, source: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (DaySegment $s) => [
            'from' => $s->fromTime(),
            'to' => $s->toTime(),
            'parent' => $s->parentId,
            'source' => $s->source->value,
        ], $this->segments);
    }

    /**
     * Relit la colonne. Une valeur absente ou illisible rend une journée unie chez `$fallback` —
     * les lignes écrites avant la migration n'ont pas de découpage, et elles n'en ont pas besoin.
     */
    public static function fromArray(mixed $raw, ?string $fallback, CustodyDaySource $fallbackSource): self
    {
        if (!\is_array($raw) || [] === $raw) {
            return self::wholeDay($fallback, $fallbackSource);
        }

        $segments = [];
        foreach ($raw as $row) {
            if (!\is_array($row)) {
                return self::wholeDay($fallback, $fallbackSource);
            }
            $from = DaySegment::minutesOf(\is_string($row['from'] ?? null) ? $row['from'] : null);
            $to = DaySegment::minutesOf(\is_string($row['to'] ?? null) ? $row['to'] : null);
            $source = CustodyDaySource::tryFrom(\is_string($row['source'] ?? null) ? $row['source'] : '');
            if (null === $from || null === $to || $from >= $to || null === $source) {
                return self::wholeDay($fallback, $fallbackSource);
            }
            $parent = \is_string($row['parent'] ?? null) ? $row['parent'] : null;
            $segments[] = new DaySegment($from, $to, $parent, $source);
        }

        return new self($segments);
    }
}
