<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Enum\ShareType;

/**
 * Répartition exacte d'un montant au centime (Lot 10 §3.1) — **service pur**, sans état ni accès
 * base : c'est la pièce que les tests balaient sur des milliers de montants.
 *
 * Méthode du **plus grand reste** : chaque bénéficiaire reçoit la part entière de sa quote-part, puis
 * les centimes manquants vont aux plus grands restes, départagés par clé croissante. Deux propriétés
 * en découlent, toutes deux testées : la somme des parts vaut **exactement** le montant (aucun
 * centime créé ni perdu), et le résultat est **reproductible** — deux calculs du même partage
 * donnent la même ligne au centime près, sans quoi le solde varierait d'un affichage à l'autre.
 */
final class ShareCalculator
{
    /**
     * @param array<string, int> $weights clé stable (identifiant de membre) → poids entier ≥ 0
     *
     * @return array<string, int> même clés → centimes, de somme exactement `$amountCents`
     */
    public function distribute(int $amountCents, array $weights): array
    {
        $total = array_sum($weights);
        if ($total <= 0 || [] === $weights) {
            return array_map(static fn (): int => 0, $weights);
        }

        $base = [];
        $remainders = [];
        foreach ($weights as $key => $weight) {
            $product = $amountCents * max(0, $weight);
            $base[$key] = intdiv($product, $total);
            $remainders[$key] = $product % $total;
        }

        // Ordre déterministe : reste décroissant, puis clé croissante. Le départage par clé est ce
        // qui rend le centime résiduel reproductible (§4.2) — sans lui, l'ordre des parts en base
        // suffirait à déplacer un centime d'un parent à l'autre entre deux lectures.
        $keys = array_keys($weights);
        usort($keys, static function (string $a, string $b) use ($remainders): int {
            return [$remainders[$b], $a] <=> [$remainders[$a], $b];
        });

        // Le manque est strictement inférieur au nombre de bénéficiaires (chaque reste l'est), donc
        // un bénéficiaire ne reçoit jamais deux centimes de rattrapage.
        $missing = $amountCents - array_sum($base);
        \assert($missing >= 0 && $missing < \count($keys));
        for ($i = 0; $i < $missing; ++$i) {
            ++$base[$keys[$i]];
        }

        return $base;
    }

    /**
     * Montant effectif dû par chaque membre d'une dépense (§3.2, `repartir_effectif`).
     *
     * - `percent` : les pourcentages servent de poids — l'arrondi est absorbé par le plus grand reste ;
     * - `amount`  : les valeurs saisies **sont** les montants, aucun ré-arrondi (leur somme est déjà
     *   validée égale au total à l'écriture).
     *
     * Les parts dont le membre a été anonymisé (FK `SET NULL`) **comptent dans la répartition** mais
     * sont retirées du résultat : la somme des parts résolubles tombe alors sous le total, et
     * {@see BalanceCalculator} s'en sert pour écarter la dépense du solde plutôt que de lui inventer
     * un débiteur. Les retirer avant le calcul reporterait leur quote-part sur les survivants.
     *
     * @return array<string, int> identifiant de membership → centimes dus
     */
    public function effective(Expense $expense): array
    {
        $shares = $expense->getShares();
        if (0 === $shares->count()) {
            return [];
        }

        // Les parts sans titulaire gardent leur **poids** dans le calcul, sous une clé de travail,
        // et ne sont retirées qu'à la fin. Les omettre d'emblée reporterait leur quote-part sur les
        // survivants : anonymiser un parent ferait alors mécaniquement doubler la dette de l'autre.
        $weights = [];
        $orphans = [];
        $index = 0;
        $type = null;
        foreach ($shares as $share) {
            $membership = $share->getMembership();
            $key = null !== $membership ? (string) $membership->getId() : \sprintf('#orphan-%d', $index++);
            if (null === $membership) {
                $orphans[$key] = true;
            }
            $weights[$key] = $share->getValue();
            $type = $share->getShareType();
        }

        $effective = ShareType::Amount === $type
            ? $weights // montants figés : la valeur saisie EST le montant dû (§3.1.C)
            : $this->distribute($expense->getAmountCents(), $weights);

        return array_diff_key($effective, $orphans);
    }
}
