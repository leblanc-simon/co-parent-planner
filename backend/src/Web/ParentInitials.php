<?php

declare(strict_types=1);

namespace App\Web;

/**
 * Calcule le **marqueur non chromatique** distinguant les deux parents d'un planning de garde
 * (cdc §8, « Attribution parent A / parent B »).
 *
 * Pourquoi ce service existe : les fonds et bordures A/B prescrits par le §8 (`--accent-soft`
 * `#FBF4EE` contre `--positive-soft` `#DCECE8`) ne se distinguent que par la **teinte** — leur
 * contraste de luminance est de 1,12:1. Dès que le canal chromatique tombe (impression en niveaux
 * de gris, daltonisme rouge-vert, `forced-colors: active` qui écrase les fonds), le planning
 * devient illisible : WCAG 1.4.1. L'initiale du parent rétablit l'information dans un canal
 * textuel, qui survit aux trois cas.
 *
 * La **règle est normative** (cdc §8) et volontairement dupliquée côté Flutter
 * (`mobile/lib/features/custody/presentation/parent_initials.dart`) : les deux clients doivent
 * afficher le même marqueur pour le même couple de parents. Les jeux de cas des tests jumeaux
 * (`ParentInitialsTest` / `parent_initials_test.dart`) sont identiques ; les faire diverger est le
 * seul risque de cette duplication, assumée pour éviter d'exposer une donnée de présentation dans
 * le contrat OpenAPI.
 */
final class ParentInitials
{
    /**
     * Longueur maximale d'un marqueur : **deux lettres**.
     *
     * Au-delà, deux prénoms qui partagent encore leur préfixe sont traités comme identiques et
     * retombent sur « A »/« B ». Ce plafond est une contrainte de rendu, pas d'algorithme : le
     * marqueur s'inscrit dans une case de calendrier de 53 px au pire cas, déjà occupée par le
     * numéro du jour, la mention « vac. » et la pastille de famille liée. Laisser filer la longueur
     * produisait des marqueurs comme « Web2 » dès que les deux parents n'ont pas de nom renseigné et
     * retombent sur leur e-mail.
     */
    private const int MAX_LENGTH = 2;

    /**
     * Marqueurs des deux parents, garantis **différents l'un de l'autre**.
     *
     * Les entrées sont des noms d'affichage (`full_name`, ou l'e-mail en repli — c'est déjà ce que
     * font les gabarits). L'initiale s'allonge d'une lettre tant que les deux parents collident
     * (« Sophie » / « Simon » → « So » / « Si »), et retombe sur « A » / « B » quand aucune longueur
     * ne les sépare (deux parents réellement homonymes, ou un nom vide).
     *
     * @return array{A: string, B: string}
     */
    public function pair(string $nameA, string $nameB): array
    {
        $firstA = self::firstName($nameA);
        $firstB = self::firstName($nameB);

        if ('' === $firstA || '' === $firstB) {
            return ['A' => 'A', 'B' => 'B'];
        }

        for ($length = 1; $length <= self::MAX_LENGTH; ++$length) {
            $markerA = self::prefix($firstA, $length);
            $markerB = self::prefix($firstB, $length);

            if ($markerA !== $markerB) {
                return ['A' => $markerA, 'B' => $markerB];
            }

            // Préfixes égaux alors que l'un des deux prénoms est épuisé : allonger ne servira plus.
            if (mb_strlen($firstA) <= $length && mb_strlen($firstB) <= $length) {
                break;
            }
        }

        return ['A' => 'A', 'B' => 'B'];
    }

    /**
     * Premier mot du nom d'affichage. Un e-mail (repli quand `full_name` est absent) est réduit à sa
     * partie locale, dont on retire les séparateurs usuels : `marc.durand@x.fr` → `marc`.
     */
    private static function firstName(string $displayName): string
    {
        $name = trim($displayName);
        if ('' === $name) {
            return '';
        }

        if (str_contains($name, '@')) {
            $name = substr($name, 0, (int) strpos($name, '@'));
            $name = (string) preg_replace('/[._\-+].*$/', '', $name);
        }

        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return $parts[0] ?? '';
    }

    /** Préfixe de `$length` lettres, capitale initiale et suite en minuscules : « So », non « SO ». */
    private static function prefix(string $firstName, int $length): string
    {
        $prefix = mb_substr($firstName, 0, $length);

        return mb_strtoupper(mb_substr($prefix, 0, 1)).mb_strtolower(mb_substr($prefix, 1));
    }
}
