<?php

declare(strict_types=1);

namespace App\Dto\Expense;

/**
 * Ligne de répartition par catégorie (§3.3). Toutes les catégories sont présentes, **y compris à
 * zéro** : une légende dont les lignes apparaissent et disparaissent au fil des saisies se lit mal.
 */
final class ExpenseCategoryTotalResource
{
    public string $category = '';
    public int $amountCents = 0;
    public int $count = 0;
    /** Part dans le total engagé, en pourcentage entier (dérivé d'affichage, ±1 sur la somme). */
    public int $percent = 0;
}
