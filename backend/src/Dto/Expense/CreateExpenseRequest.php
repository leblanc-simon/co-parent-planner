<?php

declare(strict_types=1);

namespace App\Dto\Expense;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création d'une dépense (US-DEP-1). Le format de base est validé par le Validator ; la cohérence
 * métier (payeur parent actif, enfants de la famille, homogénéité et somme des parts, bornes du
 * montant) l'est par l'`ExpenseWriter`, qui répond 422 avec une clé ICU.
 *
 * `shares` omis ⇒ **partage 50/50** matérialisé entre les parents (§3.1.A).
 */
final class CreateExpenseRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    #[Assert\NotBlank]
    public ?string $category = null;

    #[Assert\NotBlank]
    public ?string $payerMembershipId = null;

    /** Montant total en **centimes entiers** (> 0, ≤ 1 000 000 €). */
    #[Assert\NotNull]
    public ?int $amountCents = null;

    /** Jour civil de la dépense (AAAA-MM-JJ), interprété dans le fuseau de la famille (§4.1). */
    #[Assert\NotBlank]
    public ?string $expenseDate = null;

    /**
     * Enfant(s) concerné(s) — au moins un (§4.6) : une dépense n'a pas de cible « toute la famille ».
     *
     * @var list<string>
     */
    public array $childIds = [];

    /** @var list<ShareInput> partage personnalisé ; vide = 50/50 entre parents */
    public array $shares = [];
}
