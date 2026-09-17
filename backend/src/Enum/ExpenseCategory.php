<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie d'une dépense partagée (cdc §2.7, §8 ; Lot 10 §5.1). Codes stables ; les libellés
 * affichés sont localisés (i18n `expense.category.*`), jamais stockés.
 *
 * `sante` est une **étiquette de classification budgétaire**, jamais un champ typé de données de
 * santé (invariant cdc §1.4.1 / Lot 10 §7.7) : elle qualifie une ligne de compte, pas un état de
 * l'enfant.
 */
enum ExpenseCategory: string
{
    case Sante = 'sante';
    case Scolarite = 'scolarite';
    case Activites = 'activites';
    case Vetements = 'vetements';
    case Autre = 'autre';
}
