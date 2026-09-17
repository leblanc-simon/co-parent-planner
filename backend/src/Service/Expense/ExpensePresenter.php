<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Dto\Expense\ExpenseResource;
use App\Entity\Expense;
use App\Entity\Membership;
use App\Service\Family\Household;

/**
 * Met en forme une dépense pour le contrat (Lot 10 §6).
 *
 * Centralisé parce que quatre opérations renvoient la même ressource (création, modification,
 * rappel, liste) : les y recomposer séparément, c'est laisser deux d'entre elles dériver — le nom du
 * payeur ou le montant effectif d'une part y manquerait sans que rien ne le signale.
 *
 * Le nom d'affichage passe par {@see Household::displayName()}, source unique du projet (nom
 * complet déchiffré, à défaut l'e-mail).
 */
final class ExpensePresenter
{
    public function __construct(
        private readonly ShareCalculator $shares,
        private readonly ExpenseReminder $reminder,
        private readonly Household $household,
    ) {
    }

    public function one(Expense $expense): ExpenseResource
    {
        return ExpenseResource::fromExpense(
            $expense,
            $this->shares->effective($expense),
            fn (?Membership $m): string => $this->household->displayName($m),
            $this->reminder->availableAt($expense),
        );
    }

    /**
     * @param list<Expense> $expenses
     *
     * @return list<ExpenseResource>
     */
    public function many(array $expenses): array
    {
        return array_map(fn (Expense $e): ExpenseResource => $this->one($e), $expenses);
    }
}
