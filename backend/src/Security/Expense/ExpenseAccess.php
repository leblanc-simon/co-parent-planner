<?php

declare(strict_types=1);

namespace App\Security\Expense;

use App\Entity\Expense;
use App\Entity\Family;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\ExpenseNotFoundException;
use App\Repository\ExpenseRepository;
use App\Security\FamilyContext;
use App\Security\FamilyScope;
use Symfony\Component\Uid\Uuid;

/**
 * Résolution + autorisation des ressources de dépense (Lot 10 §3.4, §3.6, §4.5).
 *
 * Trois gardes, dans cet ordre, qui n'est pas interchangeable :
 *  1. **scope famille** (`X-Family-Id`) via {@see FamilyScope} ;
 *  2. **module activé** (`Family.expensesEnabled`) → sinon 404 : le module éteint, la ressource
 *     n'existe pas pour cette famille. Répondre 403 ici apprendrait à un tiers qu'elle existe
 *     ailleurs ; c'est aussi pourquoi ce test précède le Voter, qui répondrait 403 ;
 *  3. **Voter `expenses`** au niveau requis (le rôle `parent` force `edit`, l'enfant est exclu quoi
 *     qu'en dise son `Right` — {@see \App\Entity\Membership::effectiveLevel()}).
 */
final class ExpenseAccess
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly ExpenseRepository $expenses,
    ) {
    }

    /** Lecture : exige le module actif et `expenses:view`. */
    public function read(): FamilyContext
    {
        return $this->guard(RightLevel::View);
    }

    /** Mutation : exige le module actif et `expenses:edit`. */
    public function write(): FamilyContext
    {
        return $this->guard(RightLevel::Edit);
    }

    /**
     * Charge une dépense **de la famille active** (anti-IDOR §4.5) : un identifiant valide mais
     * appartenant à une autre famille est indistinguable d'un identifiant inexistant.
     */
    public function load(mixed $id, Family $family): Expense
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new ExpenseNotFoundException();
        }

        $expense = $this->expenses->find(Uuid::fromString($id));
        if (!$expense instanceof Expense || !$expense->getFamily()->getId()->equals($family->getId())) {
            throw new ExpenseNotFoundException();
        }

        return $expense;
    }

    private function guard(RightLevel $level): FamilyContext
    {
        $ctx = $this->scope->require();

        if (!$ctx->family->isExpensesEnabled()) {
            throw new ExpenseNotFoundException();
        }

        $this->scope->denyUnless($ctx, RightDomain::Expenses, $level);

        return $ctx;
    }
}
