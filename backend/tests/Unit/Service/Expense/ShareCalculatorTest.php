<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\ShareType;
use App\Service\Expense\ShareCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Répartition au centime (Lot 10 §3.1) — l'invariant qui porte tout le module : quoi qu'on partage,
 * **la somme des parts vaut exactement le montant**. Un centime créé ou perdu ici se propagerait au
 * solde, aux agrégats et à l'e-mail de rappel sans qu'aucun écran ne le signale.
 */
#[CoversClass(ShareCalculator::class)]
final class ShareCalculatorTest extends TestCase
{
    private ShareCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new ShareCalculator();
    }

    /**
     * Balayage : pour tout montant de 1 à 2 000 centimes et plusieurs répartitions, la somme des
     * parts est exacte. C'est le test qui attrape les erreurs d'arrondi, jamais un cas isolé.
     */
    /**
     * @param array<string, int> $weights
     */
    #[DataProvider('weightSets')]
    public function testDistributionAlwaysSumsToTheExactAmount(array $weights): void
    {
        for ($amount = 1; $amount <= 2000; ++$amount) {
            $parts = $this->calculator->distribute($amount, $weights);

            self::assertSame($amount, array_sum($parts), "montant {$amount} : somme des parts inexacte");
            foreach ($parts as $cents) {
                self::assertGreaterThanOrEqual(0, $cents, 'aucune part négative');
            }
        }
    }

    /**
     * @return iterable<string, array{0: array<string, int>}>
     */
    public static function weightSets(): iterable
    {
        yield '50/50' => [['a' => 1, 'b' => 1]];
        yield '70/30' => [['a' => 70, 'b' => 30]];
        yield '100/0' => [['a' => 100, 'b' => 0]];
        yield 'trois tiers' => [['a' => 1, 'b' => 1, 'c' => 1]];
        yield 'parent unique' => [['a' => 100]];
    }

    /** Le centime résiduel d'un montant impair tombe toujours du même côté (§4.2). */
    public function testResidualCentIsDeterministic(): void
    {
        $first = $this->calculator->distribute(1, ['b-second' => 1, 'a-first' => 1]);

        for ($i = 0; $i < 20; ++$i) {
            self::assertSame($first, $this->calculator->distribute(1, ['b-second' => 1, 'a-first' => 1]));
        }

        // Restes égaux → départage par clé croissante : c'est « a-first » qui prend le centime.
        self::assertSame(['b-second' => 0, 'a-first' => 1], $first);
    }

    /** Un poids total nul ne peut pas répartir : on rend des parts nulles plutôt qu'une division par zéro. */
    public function testZeroWeightsYieldZeroParts(): void
    {
        self::assertSame(['a' => 0, 'b' => 0], $this->calculator->distribute(500, ['a' => 0, 'b' => 0]));
        self::assertSame([], $this->calculator->distribute(500, []));
    }

    /** Partage en pourcentages : l'arrondi est absorbé, la somme reste le montant exact. */
    public function testPercentSharesAreDerivedFromTheTotal(): void
    {
        $expense = $this->expense(1001, [['a', ShareType::Percent, 70], ['b', ShareType::Percent, 30]]);

        $effective = $this->calculator->effective($expense);

        self::assertSame(1001, array_sum($effective));
        self::assertSame([701, 300], array_values($effective));
    }

    /**
     * Partage en montants : les valeurs saisies **sont** les montants dus, sans ré-arrondi. Les
     * dériver du total revaudrait à contredire ce que le parent a écrit.
     */
    public function testAmountSharesAreTakenAsIs(): void
    {
        $expense = $this->expense(1000, [['a', ShareType::Amount, 333], ['b', ShareType::Amount, 667]]);

        self::assertSame([333, 667], array_values($this->calculator->effective($expense)));
    }

    /**
     * Part dont le membre a été anonymisé (FK `SET NULL`) : elle n'a plus de clé, donc la somme des
     * parts résolubles tombe sous le montant — signal dont le solde se sert pour écarter la dépense
     * plutôt que d'inventer un débiteur (§4.9).
     */
    public function testAnonymizedHolderDropsOutOfEffectiveShares(): void
    {
        $expense = $this->expense(1000, [['a', ShareType::Percent, 50], ['b', ShareType::Percent, 50]]);
        $expense->getShares()->get(1)?->setMembership(null);

        $effective = $this->calculator->effective($expense);

        self::assertCount(1, $effective);
        self::assertLessThan(1000, array_sum($effective));
    }

    /**
     * @param list<array{0: string, 1: ShareType, 2: int}> $shares
     */
    private function expense(int $amountCents, array $shares): Expense
    {
        $family = (new Family())->setName('Test')->setType(FamilyType::Separated)->setExpensesEnabled(true);
        $expense = (new Expense())->setFamily($family)->setTitle('Cantine')->setAmountCents($amountCents);

        foreach ($shares as [$label, $type, $value]) {
            $member = (new Membership())->setRole(MembershipRole::Parent)->setFamily($family);
            $expense->addShare(
                (new ExpenseShare())->setMembership($member)->setShareType($type)->setValue($value)
            );
            unset($label);
        }

        return $expense;
    }
}
