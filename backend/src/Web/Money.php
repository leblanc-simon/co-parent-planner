<?php

declare(strict_types=1);

namespace App\Web;

/**
 * Mise en forme monétaire du front web (Lot 10 §4.10).
 *
 * Les montants circulent en **centimes entiers** de bout en bout ; la division par 100 n'a lieu
 * qu'ici, au dernier moment, pour l'affichage. Le format vient d'ICU (`NumberFormatter`) et non d'un
 * `number_format` maison : la position du symbole, le séparateur de milliers et la virgule décimale
 * changent d'une langue à l'autre, et « 1 234,56 € » codé en dur ne survivrait pas à l'ajout d'une
 * locale.
 */
final class Money
{
    public static function format(int $cents, string $locale = 'fr-FR', string $currency = 'EUR'): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($cents / 100, $currency);

        // Repli défensif : ICU peut échouer sur une devise inconnue. Mieux vaut un montant brut
        // qu'une case vide là où l'utilisateur attend une somme.
        return false !== $formatted ? $formatted : \sprintf('%.2f %s', $cents / 100, $currency);
    }
}
