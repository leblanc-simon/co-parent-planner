<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'une part de dépense (cdc §5.2 ; Lot 10 §3.1). Les parts d'une même dépense sont
 * **homogènes** : toutes en pourcentage, ou toutes en montants — le mélange est refusé (422).
 *
 * - `percent` : `value` ∈ [0,100], somme = 100 ; le montant effectif est dérivé du total.
 * - `amount`  : `value` en centimes entiers, somme = `amountCents` ; le montant effectif **est** la
 *   valeur saisie (aucun ré-arrondi).
 */
enum ShareType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
}
