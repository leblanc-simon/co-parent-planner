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
use App\Service\Expense\BalanceCalculator;
use App\Service\Expense\ShareCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Solde « qui doit quoi » (Lot 10 §3.2). Deux invariants s'y jouent : la **conservation**
 * (`Σ net == 0` — l'argent ne se crée ni ne se perd entre les parents) et l'**exclusion des dépenses
 * réglées**, qui sont réputées remboursées.
 */
#[CoversClass(BalanceCalculator::class)]
final class BalanceCalculatorTest extends TestCase
{
    private Family $family;
    private Membership $alice;
    private Membership $bob;
    private BalanceCalculator $balance;

    protected function setUp(): void
    {
        $this->family = (new Family())->setName('Test')->setType(FamilyType::Separated)->setExpensesEnabled(true);
        $this->alice = (new Membership())->setRole(MembershipRole::Parent)->setFamily($this->family);
        $this->bob = (new Membership())->setRole(MembershipRole::Parent)->setFamily($this->family);
        $this->balance = new BalanceCalculator(new ShareCalculator());
    }

    public function testPayerIsOwedTheOtherHalf(): void
    {
        $result = $this->balance->compute($this->parents(), [$this->fiftyFifty(10000, $this->alice)]);

        self::assertSame(0, array_sum($result->net), 'conservation : la somme des soldes est nulle');
        self::assertSame(5000, $result->amountCents);
        self::assertSame((string) $this->bob->getId(), $result->debtorMembershipId);
        self::assertSame((string) $this->alice->getId(), $result->creditorMembershipId);
        self::assertFalse($result->isBalanced());
    }

    /** Deux dépenses symétriques s'annulent : personne ne doit rien, sans arrondi résiduel. */
    public function testOpposedExpensesCancelOut(): void
    {
        $result = $this->balance->compute($this->parents(), [
            $this->fiftyFifty(4000, $this->alice),
            $this->fiftyFifty(4000, $this->bob),
        ]);

        self::assertTrue($result->isBalanced());
        self::assertNull($result->debtorMembershipId);
        self::assertSame(0, array_sum($result->net));
    }

    /** Une dépense réglée sort du solde (§3.2) — mais pas des totaux, que calcule l'agrégateur. */
    public function testSettledExpensesLeaveTheBalance(): void
    {
        $settled = $this->fiftyFifty(10000, $this->alice)->setSettled(true);

        self::assertTrue($this->balance->compute($this->parents(), [$settled])->isBalanced());
    }

    /**
     * Montant impair : le centime résiduel reste dans le solde sans jamais casser la conservation.
     */
    public function testOddAmountKeepsConservation(): void
    {
        $result = $this->balance->compute($this->parents(), [$this->fiftyFifty(1001, $this->alice)]);

        self::assertSame(0, array_sum($result->net));
        self::assertContains($result->amountCents, [500, 501], 'la moitié d\'un montant impair');
    }

    /**
     * Parent unique : il porte 100 % de ce qu'il avance, donc ne se doit rien à lui-même. Le cas est
     * défini, pas une erreur (§4.6).
     */
    public function testSingleParentFamilyIsAlwaysBalanced(): void
    {
        $expense = (new Expense())->setFamily($this->family)->setTitle('Cantine')->setAmountCents(5000)
            ->setPayerMembership($this->alice);
        $expense->addShare((new ExpenseShare())->setMembership($this->alice)->setShareType(ShareType::Percent)->setValue(100));

        $result = $this->balance->compute([$this->alice], [$expense]);

        self::assertTrue($result->isBalanced());
        self::assertSame(0, $result->net[(string) $this->alice->getId()]);
    }

    /**
     * Compte anonymisé : la dépense sort du solde **entière**, et le dit. La compter à moitié
     * afficherait une dette due à personne et casserait la conservation (§4.9).
     */
    public function testExpenseWithAnonymizedPartyIsExcludedFromBalance(): void
    {
        $orphaned = $this->fiftyFifty(10000, $this->alice);
        $orphaned->getShares()->get(1)?->setMembership(null);

        $result = $this->balance->compute($this->parents(), [$orphaned, $this->fiftyFifty(2000, $this->alice)]);

        self::assertSame(1, $result->excludedExpenseCount);
        self::assertSame(1000, $result->amountCents, 'seule la dépense intacte pèse sur le solde');
        self::assertSame(0, array_sum($result->net));
    }

    /** Payeur anonymisé : même traitement — il n'y a plus personne à qui rembourser. */
    public function testExpenseWithAnonymizedPayerIsExcluded(): void
    {
        $result = $this->balance->compute($this->parents(), [
            $this->fiftyFifty(10000, $this->alice)->setPayerMembership(null),
        ]);

        self::assertSame(1, $result->excludedExpenseCount);
        self::assertTrue($result->isBalanced());
    }

    /** @return list<Membership> */
    private function parents(): array
    {
        return [$this->alice, $this->bob];
    }

    private function fiftyFifty(int $amountCents, Membership $payer): Expense
    {
        $expense = (new Expense())
            ->setFamily($this->family)
            ->setTitle('Cantine')
            ->setAmountCents($amountCents)
            ->setPayerMembership($payer);

        foreach ([$this->alice, $this->bob] as $parent) {
            $expense->addShare(
                (new ExpenseShare())->setMembership($parent)->setShareType(ShareType::Percent)->setValue(50)
            );
        }

        return $expense;
    }
}
