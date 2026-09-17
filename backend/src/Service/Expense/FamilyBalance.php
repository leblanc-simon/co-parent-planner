<?php

declare(strict_types=1);

namespace App\Service\Expense;

/**
 * Solde inter-parents d'une famille (Lot 10 §3.2) — résultat **pur** du calcul, sans mise en forme.
 *
 * `net` porte le solde signé de chaque parent en centimes : positif = on lui doit, négatif = il doit.
 * Sa somme vaut toujours 0 (conservation, invariant testé). La réduction « A doit B » n'est exposée
 * que pour deux parents ; au-delà (cas non prévu par le cdc), seul `net` fait foi.
 */
final readonly class FamilyBalance
{
    /**
     * @param array<string, int> $net                  identifiant de membership parent → centimes signés
     * @param int                $excludedExpenseCount dépenses écartées faute de partie résoluble (§4.9)
     */
    public function __construct(
        public array $net,
        public ?string $debtorMembershipId,
        public ?string $creditorMembershipId,
        public int $amountCents,
        public int $excludedExpenseCount = 0,
    ) {
    }

    public function isBalanced(): bool
    {
        return 0 === $this->amountCents;
    }
}
