<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Expense;

use App\Entity\Expense;
use App\Entity\Family;
use App\Enum\ExpenseCategory;
use App\Enum\FamilyType;
use App\Service\Expense\ExpenseAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Totaux et répartition par catégorie (Lot 10 §3.3). Contrairement au solde, ils portent sur **tout
 * l'historique** : une dépense remboursée a bien été engagée.
 */
#[CoversClass(ExpenseAggregator::class)]
final class ExpenseAggregatorTest extends TestCase
{
    private ExpenseAggregator $aggregator;
    private Family $family;

    protected function setUp(): void
    {
        $this->aggregator = new ExpenseAggregator();
        $this->family = (new Family())->setName('Test')->setType(FamilyType::Separated)->setExpensesEnabled(true);
    }

    public function testSettledExpensesStayInTheTotalButSplitOut(): void
    {
        $totals = $this->aggregator->aggregate([
            $this->expense(6000, ExpenseCategory::Scolarite, settled: false),
            $this->expense(4000, ExpenseCategory::Sante, settled: true),
        ]);

        self::assertSame(10000, $totals->totalCents);
        self::assertSame(4000, $totals->settledCents);
        self::assertSame(6000, $totals->unsettledCents);
        self::assertSame(2, $totals->count);
    }

    /** Toutes les catégories figurent au résultat, même à zéro : la légende ne doit pas clignoter. */
    public function testEveryCategoryIsPresentEvenEmpty(): void
    {
        $totals = $this->aggregator->aggregate([$this->expense(1000, ExpenseCategory::Vetements)]);

        self::assertCount(\count(ExpenseCategory::cases()), $totals->byCategory);
        self::assertSame(0, $totals->byCategory['sante']['amountCents']);
        self::assertSame(1, $totals->byCategory['vetements']['count']);
    }

    /**
     * La part en pourcentage est un dérivé d'affichage : sa somme peut s'écarter de 100 de ±1 après
     * arrondi, et c'est admis — ce n'est pas un montant, donc aucun centime n'est en jeu.
     */
    public function testPercentIsADisplayDerivativeAndMayNotSumTo100(): void
    {
        $totals = $this->aggregator->aggregate([
            $this->expense(1000, ExpenseCategory::Sante),
            $this->expense(1000, ExpenseCategory::Scolarite),
            $this->expense(1000, ExpenseCategory::Activites),
        ]);

        $sum = array_sum(array_map(
            static fn (ExpenseCategory $c): int => $totals->percentOf($c),
            ExpenseCategory::cases(),
        ));

        self::assertSame(33, $totals->percentOf(ExpenseCategory::Sante));
        self::assertEqualsWithDelta(100, $sum, 1.0);
    }

    public function testEmptyHistoryHasNoPercent(): void
    {
        $totals = $this->aggregator->aggregate([]);

        self::assertSame(0, $totals->totalCents);
        self::assertSame(0, $totals->percentOf(ExpenseCategory::Autre), 'aucune division par zéro');
    }

    private function expense(int $amountCents, ExpenseCategory $category, bool $settled = false): Expense
    {
        return (new Expense())
            ->setFamily($this->family)
            ->setTitle('Dépense')
            ->setCategory($category)
            ->setAmountCents($amountCents)
            ->setSettled($settled);
    }
}
