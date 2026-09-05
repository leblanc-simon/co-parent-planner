<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Palette **fermée** des couleurs de garde (cdc §8.2, évolution B).
 *
 * Huit teintes, chacune un triplet *bordure / fond doux / encre* dont le contraste a été mesuré pour
 * que l'initiale du parent — le marqueur non chromatique qu'impose le §8.1 — reste lisible sur le
 * fond de case. Un sélecteur libre laisserait choisir un fond sur lequel ce marqueur disparaîtrait,
 * c'est-à-dire l'information elle-même : la personnalisation ne dispense d'aucune règle du §8.1.
 *
 * Ce qui est stocké est la **clé**, jamais l'hexadécimal : les valeurs ci-dessous peuvent être
 * ajustées sans migration, et une teinte retirée du catalogue se repère immédiatement.
 */
enum CustodyPalette: string
{
    case Terracotta = 'terracotta';
    case Teal = 'teal';
    case Blue = 'blue';
    case Purple = 'purple';
    case Amber = 'amber';
    case Pink = 'pink';
    case Green = 'green';
    case Slate = 'slate';

    /** Teinte par défaut du parent A (§8). */
    public const self DEFAULT_A = self::Terracotta;
    /** Teinte par défaut du parent B (§8). */
    public const self DEFAULT_B = self::Teal;

    /** @return array{border: string, soft: string, ink: string} */
    public function colors(): array
    {
        return match ($this) {
            self::Terracotta => ['border' => '#C2643D', 'soft' => '#F6E7DE', 'ink' => '#A8512B'],
            self::Teal => ['border' => '#2E7D71', 'soft' => '#DCECE8', 'ink' => '#226B60'],
            self::Blue => ['border' => '#5E7CB8', 'soft' => '#E4EAF5', 'ink' => '#48628F'],
            // Volontairement distinct du #9C6FB0 de la famille liée : deux violets identiques
            // rendraient un marqueur d'overlay indiscernable d'une case de garde (constat 22 de la
            // liste de finition web).
            self::Purple => ['border' => '#8E63A8', 'soft' => '#EDE4F2', 'ink' => '#6F4A86'],
            self::Amber => ['border' => '#B8862F', 'soft' => '#F6EDDA', 'ink' => '#8E671F'],
            self::Pink => ['border' => '#B85C7E', 'soft' => '#F7E4EA', 'ink' => '#8F4460'],
            self::Green => ['border' => '#5E8C55', 'soft' => '#E5EFE2', 'ink' => '#456B3E'],
            self::Slate => ['border' => '#5F6B7A', 'soft' => '#E7EAEE', 'ink' => '#47515E'],
        };
    }

    /** Libellé français ; l'UI web est livrée en français seul (décision Lot 5). */
    public function label(): string
    {
        return match ($this) {
            self::Terracotta => 'Terracotta',
            self::Teal => 'Teal',
            self::Blue => 'Bleu',
            self::Purple => 'Violet',
            self::Amber => 'Ambre',
            self::Pink => 'Rose',
            self::Green => 'Vert',
            self::Slate => 'Ardoise',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
