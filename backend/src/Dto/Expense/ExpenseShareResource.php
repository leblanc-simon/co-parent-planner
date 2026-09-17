<?php

declare(strict_types=1);

namespace App\Dto\Expense;

/**
 * Part d'une dépense telle qu'exposée (Lot 10 §3.1).
 *
 * Deux lectures coexistent, et il faut les deux : `shareType`/`value` sont **ce qui a été saisi** —
 * ce que le formulaire doit ré-afficher à l'édition — tandis que `effectiveCents` est le montant
 * réellement dû, dérivé au centime près. Exposer seulement le premier obligerait chaque client à
 * refaire l'arrondi (et à le refaire pareil) ; exposer seulement le second rendrait un partage
 * « 70 / 30 » indistinguable d'un partage en montants fixes.
 */
final class ExpenseShareResource
{
    public string $membershipId = '';
    public string $memberName = '';
    public string $shareType = '';
    public int $value = 0;
    public int $effectiveCents = 0;
}
