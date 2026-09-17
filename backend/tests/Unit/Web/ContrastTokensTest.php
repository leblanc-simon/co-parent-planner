<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use PHPUnit\Framework\TestCase;

/**
 * Verrouille le mode **contraste renforcé** de la feuille de style (cdc §8).
 *
 * La palette par défaut reste celle du §8, dont plusieurs paires échouent au niveau AA — décision
 * produit assumée et documentée. Le bloc `@media (prefers-contrast: more)` est la sortie de secours
 * offerte à qui la demande au niveau de son système : s'il dérive, elle ne sert plus à rien, et
 * l'écart passerait d'autant plus inaperçu que personne ne navigue dans ce mode au quotidien.
 *
 * Le test recalcule les ratios WCAG 2.1 (§1.4.3) depuis le CSS lui-même : aucune valeur attendue
 * n'est recopiée ici, seul le seuil l'est.
 */
final class ContrastTokensTest extends TestCase
{
    /** Seuil AA pour du texte courant (les tokens visés servent à des libellés de 10 à 14 px). */
    private const float AA_NORMAL_TEXT = 4.5;

    /** Tokens de fond sur lesquels un texte de l'application peut être posé. */
    private const array SURFACE_TOKENS = ['--app-bg', '--surface', '--card'];

    /**
     * Tokens de texte que le mode doit corriger, et le fond de pastille supplémentaire à vérifier
     * pour les catégories (le libellé y est posé sur son propre fond).
     */
    private const array TEXT_TOKENS = [
        '--muted' => null,
        '--muted-2' => null,
        '--cat-sport' => '--cat-sport-bg',
        '--cat-music' => '--cat-music-bg',
        '--cat-school' => '--cat-school-bg',
        '--cat-health' => '--cat-health-bg',
        '--cat-other' => '--cat-other-bg',
        // Dépenses (Lot 10) : « Vêtements » est la seule teinte de catégorie qui leur soit propre.
        '--cat-clothing' => '--cat-clothing-bg',
    ];

    public function testHighContrastModeMeetsAaOnEverySurface(): void
    {
        $root = self::declarations(self::block('/:root\s*\{(.+?)\}/s'));
        $highContrast = self::declarations(
            self::block('/@media\s*\(prefers-contrast:\s*more\)\s*\{\s*:root\s*\{(.+?)\}/s'),
        );

        foreach (self::TEXT_TOKENS as $token => $pillBackground) {
            self::assertArrayHasKey(
                $token,
                $highContrast,
                \sprintf('Le mode contraste renforcé ne redéfinit pas %s : il resterait sous le seuil AA.', $token),
            );

            $foreground = $highContrast[$token];
            $surfaces = self::SURFACE_TOKENS;
            if (null !== $pillBackground) {
                $surfaces[] = $pillBackground;
            }

            foreach ($surfaces as $surfaceToken) {
                self::assertArrayHasKey($surfaceToken, $root, \sprintf('Fond %s absent de :root.', $surfaceToken));

                $ratio = self::contrastRatio($foreground, $root[$surfaceToken]);
                self::assertGreaterThanOrEqual(
                    self::AA_NORMAL_TEXT,
                    $ratio,
                    \sprintf(
                        '%s (%s) sur %s (%s) : %.2f:1, sous le seuil AA de %.1f:1.',
                        $token,
                        $foreground,
                        $surfaceToken,
                        $root[$surfaceToken],
                        $ratio,
                        self::AA_NORMAL_TEXT,
                    ),
                );
            }
        }
    }

    /**
     * Le marqueur non chromatique de l'attribution parent A/B doit rester déclaré (cdc §8) : c'est
     * lui qui rend le planning lisible sans la couleur, et son rendu est vérifié côté fonctionnel
     * ({@see \App\Tests\Functional\Web\WebPagesTest::testGardeCarriesNonColourParentMarker}).
     */
    public function testNonColourParentMarkerIsStyled(): void
    {
        $css = self::css();

        self::assertStringContainsString('.garde-day__parent', $css);
        self::assertStringContainsString('.home-custody__parent', $css);
        // Le mode contraste forcé écrase les fonds : sans ce bloc, la légende perdrait son contour.
        self::assertMatchesRegularExpression('/@media\s*\(forced-colors:\s*active\)/', $css);
    }

    private static function css(): string
    {
        $path = \dirname(__DIR__, 3).'/assets/styles/app.css';
        $css = file_get_contents($path);
        self::assertIsString($css, \sprintf('Feuille de style introuvable : %s', $path));

        return $css;
    }

    /** Contenu du premier bloc correspondant au motif fourni. */
    private static function block(string $pattern): string
    {
        self::assertSame(1, preg_match($pattern, self::css(), $matches), \sprintf('Bloc CSS introuvable : %s', $pattern));

        return $matches[1];
    }

    /**
     * Déclarations `--token: #HEX` d'un bloc, indexées par token.
     *
     * @return array<string, string>
     */
    private static function declarations(string $block): array
    {
        preg_match_all('/(--[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{6})\s*;/', $block, $matches, \PREG_SET_ORDER);

        $declarations = [];
        foreach ($matches as $match) {
            $declarations[$match[1]] = strtoupper($match[2]);
        }

        return $declarations;
    }

    /** Ratio de contraste WCAG 2.1 entre deux couleurs `#RRGGBB`. */
    private static function contrastRatio(string $a, string $b): float
    {
        $lighter = max(self::relativeLuminance($a), self::relativeLuminance($b));
        $darker = min(self::relativeLuminance($a), self::relativeLuminance($b));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /** Luminance relative WCAG (composantes sRGB linéarisées). */
    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $value = (int) hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
