<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\Membership;

/**
 * Solde « qui doit quoi » entre parents (Lot 10 §3.2).
 *
 * Principe : pour chaque dépense **non réglée**, le payeur a avancé le total et chaque parent doit sa
 * part. Le solde net d'un parent est donc (ce qu'il a avancé) − (ce qu'il devait). Marquer une
 * dépense réglée la sort du solde sans toucher aux totaux historiques (§3.3).
 *
 * **Dépenses écartées** : une dépense dont le payeur ou l'un des titulaires de part a été anonymisé
 * (FK `SET NULL`, §4.9) n'a plus de contrepartie identifiable. La compter à moitié casserait la
 * conservation `Σ net == 0` et afficherait une dette due à personne ; elle est donc retirée du solde
 * — et comptée à part, pour que l'écran puisse dire pourquoi le solde ne recouvre pas tout
 * l'historique — tout en restant dans le total engagé et la répartition par catégorie.
 */
final class BalanceCalculator
{
    public function __construct(private readonly ShareCalculator $shares)
    {
    }

    /**
     * @param list<Membership> $parents  parents actifs de la famille : eux seuls entrent au solde
     *                                   (cdc §2.7 — un beau-parent saisit sans jamais y figurer)
     * @param list<Expense>    $expenses toutes les dépenses de la famille (le filtre « non réglées » est fait ici)
     */
    public function compute(array $parents, array $expenses): FamilyBalance
    {
        $net = [];
        foreach ($parents as $parent) {
            $net[(string) $parent->getId()] = 0;
        }

        $excluded = 0;
        foreach ($expenses as $expense) {
            if ($expense->isSettled()) {
                continue;
            }

            $payer = $expense->getPayerMembership();
            $due = $this->shares->effective($expense);

            if (null === $payer || !$this->isFullyResolvable($expense, $due)) {
                ++$excluded;
                continue;
            }

            $net[(string) $payer->getId()] = ($net[(string) $payer->getId()] ?? 0) + $expense->getAmountCents();
            foreach ($due as $membershipId => $cents) {
                $net[$membershipId] = ($net[$membershipId] ?? 0) - $cents;
            }
        }

        return $this->reduce($net, $excluded);
    }

    /**
     * La dépense a-t-elle toutes ses parties ? On compare la somme des parts résolubles au montant :
     * un titulaire anonymisé fait chuter la somme, et c'est le seul signal fiable — une part à zéro
     * est légitime (§4.2, un parent peut porter 100 %).
     *
     * @param array<string, int> $due
     */
    private function isFullyResolvable(Expense $expense, array $due): bool
    {
        return [] !== $due && array_sum($due) === $expense->getAmountCents();
    }

    /**
     * Réduit le solde signé à la phrase « A doit B tant » : le créditeur est le parent au solde le
     * plus créditeur, le débiteur celui au solde le plus débiteur, et le montant est le plus petit
     * des deux en valeur absolue — avec deux parents, `net[A] = -net[B]` et les trois coïncident.
     * Tous les soldes nuls ⇒ comptes équilibrés (aucun nom, montant zéro).
     *
     * @param array<string, int> $net
     */
    private function reduce(array $net, int $excluded): FamilyBalance
    {
        $creditor = null;
        $debtor = null;
        $credit = 0;
        $debit = 0;

        foreach ($net as $membershipId => $cents) {
            if ($cents > $credit) {
                $credit = $cents;
                $creditor = $membershipId;
            }
            if ($cents < $debit) {
                $debit = $cents;
                $debtor = $membershipId;
            }
        }

        $amount = min($credit, -$debit);
        if (0 === $amount) {
            return new FamilyBalance($net, null, null, 0, $excluded);
        }

        return new FamilyBalance($net, $debtor, $creditor, $amount, $excluded);
    }
}
