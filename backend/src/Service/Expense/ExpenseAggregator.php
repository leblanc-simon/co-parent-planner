<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Enum\ExpenseCategory;

/**
 * Répartition par catégorie et total engagé (Lot 10 §3.3). Service **pur** : il additionne des
 * centimes entiers, donc aucun arrondi n'intervient — le seul pourcentage produit est un dérivé
 * d'affichage porté par {@see ExpenseTotals::percentOf()}.
 *
 * Toutes les catégories figurent au résultat, **y compris à zéro** : un écran qui n'afficherait que
 * les catégories mouvementées ferait apparaître et disparaître ses lignes au fil des saisies.
 */
final class ExpenseAggregator
{
    /**
     * @param list<Expense> $expenses
     */
    public function aggregate(array $expenses): ExpenseTotals
    {
        $byCategory = [];
        foreach (ExpenseCategory::cases() as $category) {
            $byCategory[$category->value] = ['amountCents' => 0, 'count' => 0];
        }

        $total = 0;
        $settled = 0;
        foreach ($expenses as $expense) {
            $cents = $expense->getAmountCents();
            $total += $cents;
            if ($expense->isSettled()) {
                $settled += $cents;
            }
            $key = $expense->getCategory()->value;
            $byCategory[$key]['amountCents'] += $cents;
            ++$byCategory[$key]['count'];
        }

        return new ExpenseTotals($total, $settled, $total - $settled, \count($expenses), $byCategory);
    }
}
