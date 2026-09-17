<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Expense\ExpenseBalanceResource;
use App\Dto\Expense\ExpenseCategoryTotalResource;
use App\Dto\Expense\ExpenseNetResource;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\ExpenseCategory;
use App\Enum\MembershipRole;
use App\Repository\ExpenseRepository;
use App\Repository\MembershipRepository;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\BalanceCalculator;
use App\Service\Expense\ExpenseAggregator;
use App\Service\Family\Household;

/**
 * US-DEP-2 — solde « qui doit quoi », répartition par catégorie et total engagé (expenses:view).
 *
 * Le solde n'est **jamais matérialisé** : il est recalculé à chaque lecture depuis les dépenses. Un
 * total stocké serait à réviser à chaque création, édition, règlement, suppression et anonymisation
 * de compte — cinq occasions de diverger, pour une addition d'entiers qui coûte moins qu'un cache.
 *
 * @implements ProviderInterface<ExpenseBalanceResource>
 */
final class BalanceProvider implements ProviderInterface
{
    public function __construct(
        private readonly ExpenseAccess $access,
        private readonly ExpenseRepository $expenses,
        private readonly BalanceCalculator $balance,
        private readonly ExpenseAggregator $aggregator,
        private readonly MembershipRepository $memberships,
        private readonly Household $household,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ExpenseBalanceResource
    {
        $ctx = $this->access->read();
        $expenses = $this->expenses->findForAggregates($ctx->family);

        $balance = $this->balance->compute($this->activeParents($ctx->family), $expenses);
        $totals = $this->aggregator->aggregate($expenses);
        $names = $this->namesByMembershipId($ctx->family);

        $resource = new ExpenseBalanceResource();
        $resource->familyId = (string) $ctx->family->getId();
        $resource->debtorMembershipId = $balance->debtorMembershipId;
        $resource->debtorName = $names[$balance->debtorMembershipId ?? ''] ?? '';
        $resource->creditorMembershipId = $balance->creditorMembershipId;
        $resource->creditorName = $names[$balance->creditorMembershipId ?? ''] ?? '';
        $resource->amountCents = $balance->amountCents;
        $resource->balanced = $balance->isBalanced();
        $resource->excludedFromBalanceCount = $balance->excludedExpenseCount;

        foreach ($balance->net as $membershipId => $cents) {
            $line = new ExpenseNetResource();
            $line->membershipId = $membershipId;
            $line->memberName = $names[$membershipId] ?? '';
            $line->netCents = $cents;
            $resource->net[] = $line;
        }

        $resource->totalCents = $totals->totalCents;
        $resource->settledCents = $totals->settledCents;
        $resource->unsettledCents = $totals->unsettledCents;
        $resource->expenseCount = $totals->count;

        foreach (ExpenseCategory::cases() as $category) {
            $line = new ExpenseCategoryTotalResource();
            $line->category = $category->value;
            $line->amountCents = $totals->byCategory[$category->value]['amountCents'];
            $line->count = $totals->byCategory[$category->value]['count'];
            $line->percent = $totals->percentOf($category);
            $resource->byCategory[] = $line;
        }

        return $resource;
    }

    /**
     * Parents actifs de la famille — seuls membres du solde (cdc §2.7).
     *
     * @return list<Membership>
     */
    private function activeParents(Family $family): array
    {
        return array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn (Membership $m): bool => MembershipRole::Parent === $m->getRole(),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function namesByMembershipId(Family $family): array
    {
        $names = [];
        foreach ($this->memberships->findActiveByFamily($family) as $member) {
            \assert($member instanceof Membership);
            $names[(string) $member->getId()] = $this->household->displayName($member);
        }

        return $names;
    }
}
