<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Enum\ExpenseCategory;

/**
 * Agrégats d'une famille (Lot 10 §3.3) : total engagé, ventilation réglé / non réglé et répartition
 * par catégorie. Contrairement au solde, ils portent sur **tout l'historique**, réglé compris — une
 * dépense remboursée reste une dépense engagée.
 */
final readonly class ExpenseTotals
{
    /**
     * @param array<string, array{amountCents: int, count: int}> $byCategory clé de catégorie → total et nombre
     */
    public function __construct(
        public int $totalCents,
        public int $settledCents,
        public int $unsettledCents,
        public int $count,
        public array $byCategory,
    ) {
    }

    /**
     * Part d'une catégorie dans le total, en pourcentage entier — **donnée d'affichage** : la somme
     * des parts peut s'écarter de 100 de ±1 après arrondi, ce qui est sans conséquence puisque ce
     * n'est pas un montant (§3.3).
     */
    public function percentOf(ExpenseCategory $category): int
    {
        if (0 === $this->totalCents) {
            return 0;
        }

        return (int) round(($this->byCategory[$category->value]['amountCents'] ?? 0) * 100 / $this->totalCents);
    }
}
