<?php

declare(strict_types=1);

namespace App\Service\Custody;

/**
 * Peinture d'une case de planning partagée entre deux parents dans la journée (cdc §8.3) : les
 * arrêts du dégradé et les positions des filets, prêts à poser en variables CSS.
 *
 * Les teintes viennent de la palette du spectateur (`--slot-a-soft` / `--slot-b-soft`, §8.2), donc
 * un repeignage des slots suit ici aussi. Les **filets** sont rendus par des pseudo-éléments à
 * bordure : `forced-colors` écrase les fonds — et avec eux le dégradé — mais force les bordures à
 * la couleur de texte du système, seul canal qui dise encore « journée partagée » en niveaux de
 * gris (§8.1).
 *
 * Une journée compte au plus trois tranches (un échange partiel encadré par le rythme), donc au
 * plus deux filets — ce que deux pseudo-éléments savent porter.
 */
final class DayPaint
{
    private function __construct(
        /** Arrêts du dégradé, tels quels dans `linear-gradient(to right, …)`. */
        public readonly string $stops,
        /** @var list<string> positions des filets, en pourcentage */
        public readonly array $boundaries,
    ) {
    }

    /**
     * @param list<array{slot: ?string, percent: float}> $segments tranches de la journée, dans l'ordre
     */
    public static function of(array $segments): ?self
    {
        if (\count($segments) < 2) {
            return null; // journée unie : la classe de slot suffit, pas de dégradé
        }

        $stops = [];
        $boundaries = [];
        $cursor = 0.0;
        foreach ($segments as $index => $segment) {
            $end = $index === \count($segments) - 1 ? 100.0 : round($cursor + $segment['percent'], 1);
            $stops[] = \sprintf('%s %s %s', self::color($segment['slot']), self::percent($cursor), self::percent($end));
            if ($end < 100.0) {
                $boundaries[] = self::percent($end);
            }
            $cursor = $end;
        }

        return new self(implode(', ', $stops), $boundaries);
    }

    private static function color(?string $slot): string
    {
        return match ($slot) {
            'A' => 'var(--slot-a-soft)',
            'B' => 'var(--slot-b-soft)',
            default => 'var(--surface)',
        };
    }

    private static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.').'%';
    }
}
