<?php

declare(strict_types=1);

namespace App\Dto\Expense;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use App\Dto\Family\FamilyResource;
use App\State\Expense\BalanceProvider;

/**
 * Solde « qui doit quoi », répartition par catégorie et total engagé (US-DEP-2, cdc §6.2).
 *
 * Deux périmètres cohabitent volontairement dans la même réponse, parce que l'écran les affiche
 * côte à côte : le **solde** ne porte que sur les dépenses non réglées (une dépense remboursée ne se
 * doit plus), tandis que le **total engagé** et la **répartition par catégorie** portent sur tout
 * l'historique (une dépense remboursée a bien été engagée).
 *
 * Tous les montants sont en centimes entiers ; `percent` est le seul dérivé d'affichage, arrondi à
 * l'unité — la somme des parts peut donc s'écarter de 100 de ±1 (§3.3).
 */
#[ApiResource(
    shortName: 'ExpenseBalance',
    operations: [
        new Get(
            uriTemplate: '/families/{familyId}/expenses/balance',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            // Pas de `read: false` ici : sur un GET, il désactive l'étape de lecture — le provider ne
            // serait jamais appelé et la réponse serait un `null` en 200.
            provider: BalanceProvider::class,
        ),
    ],
)]
class ExpenseBalanceResource
{
    /** Identifiant de la famille — le solde n'a de sens que scopé (§4.5). */
    public string $familyId = '';

    /** @var list<ExpenseNetResource> solde signé par parent (positif : on lui doit) */
    public array $net = [];

    /** Parent qui doit, `null` si les comptes sont équilibrés. */
    public ?string $debtorMembershipId = null;
    public string $debtorName = '';
    /** Parent à qui l'on doit, `null` si les comptes sont équilibrés. */
    public ?string $creditorMembershipId = null;
    public string $creditorName = '';
    /** Montant de la dette nette, en centimes ; `0` ⇔ comptes équilibrés. */
    public int $amountCents = 0;
    public bool $balanced = true;

    public int $totalCents = 0;
    public int $settledCents = 0;
    public int $unsettledCents = 0;
    public int $expenseCount = 0;

    /** @var list<ExpenseCategoryTotalResource> */
    public array $byCategory = [];

    /**
     * Dépenses non réglées écartées du solde faute de partie identifiable (compte anonymisé, §4.9).
     * Exposé parce qu'un solde amputé sans explication passerait pour un bug côté utilisateur.
     */
    public int $excludedFromBalanceCount = 0;
}
