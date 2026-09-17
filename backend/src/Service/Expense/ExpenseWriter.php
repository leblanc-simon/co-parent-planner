<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Dto\Expense\CreateExpenseRequest;
use App\Dto\Expense\ShareInput;
use App\Dto\Expense\UpdateExpenseRequest;
use App\Entity\Child;
use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\ExpenseCategory;
use App\Enum\MembershipRole;
use App\Enum\ShareType;
use App\Exception\ExpenseInputException;
use App\Repository\ChildRepository;
use App\Repository\MembershipRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Valide et construit une dépense (Lot 10 §3.1, §4.2, §4.6). Toute violation → 422 avec une clé ICU.
 *
 * Ce qui se joue ici, au-delà du formulaire :
 *  - le **payeur est un parent actif** de la famille (cdc §2.7) — un beau-parent habilité saisit la
 *    dépense, mais n'avance pas l'argent et n'entre pas dans le solde ;
 *  - les parts sont **homogènes** et somment juste (100 en pourcentage, le total en centimes) ;
 *  - les parts ne visent que des **parents** : une part au nom d'un beau-parent créerait une dette
 *    que le solde inter-parents ne saurait ni afficher ni solder ;
 *  - une dépense sans `shares` reçoit le **50/50 matérialisé** plutôt qu'un partage implicite : ce
 *    qui est stocké est ce qui sera ré-affiché à l'édition, et le calcul du solde n'a pas à deviner
 *    quels parents existaient au moment de la saisie.
 */
final class ExpenseWriter
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly ChildRepository $children,
    ) {
    }

    public function build(Family $family, Membership $creator, CreateExpenseRequest $data): Expense
    {
        $expense = (new Expense())
            ->setFamily($family)
            ->setCreatedByMembership($creator)
            ->setTitle($this->title($data->title))
            ->setCategory($this->category($data->category))
            ->setAmountCents($this->amount($data->amountCents))
            ->setExpenseDate($this->date($data->expenseDate))
            ->setPayerMembership($this->payer($family, $data->payerMembershipId));

        foreach ($this->resolveChildren($family, $data->childIds) as $child) {
            $expense->addChild($child);
        }

        $this->applyShares($expense, $family, $data->shares);

        return $expense;
    }

    /**
     * Applique un PATCH. Les champs absents (`null`) sont **inchangés** ; `shares` non vide
     * re-matérialise tout le partage. Le partage est revalidé même si seul le montant change : des
     * parts en centimes qui ne somment plus au total seraient une incohérence silencieuse (§2 US-DEP-4).
     */
    public function update(Expense $expense, Family $family, UpdateExpenseRequest $data): void
    {
        if (null !== $data->title) {
            $expense->setTitle($this->title($data->title));
        }
        if (null !== $data->category) {
            $expense->setCategory($this->category($data->category));
        }
        if (null !== $data->amountCents) {
            $expense->setAmountCents($this->amount($data->amountCents));
        }
        if (null !== $data->expenseDate) {
            $expense->setExpenseDate($this->date($data->expenseDate));
        }
        if (null !== $data->payerMembershipId) {
            $expense->setPayerMembership($this->payer($family, $data->payerMembershipId));
        }
        if (null !== $data->childIds) {
            $expense->clearChildren();
            foreach ($this->resolveChildren($family, $data->childIds) as $child) {
                $expense->addChild($child);
            }
        }
        if (null !== $data->settled) {
            $expense->setSettled($data->settled);
        }

        if (null !== $data->shares && [] !== $data->shares) {
            $this->applyShares($expense, $family, $data->shares);

            return;
        }

        // Partage inchangé : il reste à vérifier qu'il tient toujours face au nouveau montant.
        $this->assertExistingSharesStillConsistent($expense);
    }

    private function title(?string $raw): string
    {
        $title = trim((string) $raw);
        if ('' === $title) {
            throw new ExpenseInputException('expense.error.title_required');
        }
        if (mb_strlen($title) > Expense::MAX_TITLE_LENGTH) {
            throw new ExpenseInputException('expense.error.title_too_long');
        }

        return $title;
    }

    private function category(?string $raw): ExpenseCategory
    {
        $category = ExpenseCategory::tryFrom((string) $raw);
        if (null === $category) {
            throw new ExpenseInputException('expense.error.category_invalid');
        }

        return $category;
    }

    private function amount(?int $cents): int
    {
        if (null === $cents || $cents <= 0) {
            throw new ExpenseInputException('expense.error.amount_invalid');
        }
        if ($cents > Expense::MAX_AMOUNT_CENTS) {
            throw new ExpenseInputException('expense.error.amount_too_large');
        }

        return $cents;
    }

    /**
     * Date civile stricte `AAAA-MM-JJ`. La comparaison au format re-rendu rejette le 29 février d'une
     * année non bissextile, que `createFromFormat` accepterait en le reportant au 1er mars (§4.1).
     */
    private function date(?string $raw): \DateTimeImmutable
    {
        $value = (string) $raw;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new ExpenseInputException('expense.error.date_invalid');
        }

        return $date;
    }

    private function payer(Family $family, ?string $membershipId): Membership
    {
        $payer = $this->activeMember($family, (string) $membershipId);
        if (null === $payer || MembershipRole::Parent !== $payer->getRole()) {
            throw new ExpenseInputException('expense.error.payer_invalid');
        }

        return $payer;
    }

    /**
     * @param list<string> $childIds
     *
     * @return list<Child>
     */
    private function resolveChildren(Family $family, array $childIds): array
    {
        $unique = array_values(array_unique(array_map('strval', $childIds)));
        if ([] === $unique) {
            throw new ExpenseInputException('expense.error.child_required');
        }

        $byId = [];
        foreach ($this->children->findByFamily($family) as $child) {
            $byId[(string) $child->getId()] = $child;
        }

        $resolved = [];
        foreach ($unique as $id) {
            if (!isset($byId[$id])) {
                throw new ExpenseInputException('expense.error.child_invalid');
            }
            $resolved[] = $byId[$id];
        }

        return $resolved;
    }

    /**
     * Matérialise le partage : celui qui est saisi, ou le 50/50 par défaut entre les parents actifs.
     *
     * La réconciliation se fait **en place** (une part existante est mise à jour, pas remplacée).
     * Vider la collection pour la reconstruire heurterait l'unicité `(expense_id, membership_id)` :
     * Doctrine insère les nouvelles lignes avant de supprimer les anciennes, et un partage ré-édité
     * échouait en 500 alors que la requête était parfaitement valide.
     *
     * @param list<ShareInput> $inputs
     */
    private function applyShares(Expense $expense, Family $family, array $inputs): void
    {
        $wanted = [] === $inputs
            ? $this->defaultShares($family)
            : $this->validatedShares($expense, $family, $inputs);

        $existing = [];
        foreach ($expense->getShares() as $share) {
            $membership = $share->getMembership();
            $existing[null !== $membership ? (string) $membership->getId() : spl_object_hash($share)] = $share;
        }

        $kept = [];
        foreach ($wanted as [$membership, $type, $value]) {
            $id = (string) $membership->getId();
            $share = $existing[$id] ?? null;
            if (null === $share) {
                $share = (new ExpenseShare())->setMembership($membership);
                $expense->addShare($share);
            }
            $share->setShareType($type)->setValue($value);
            $kept[$id] = true;
        }

        // Ce qui n'est plus dans le partage disparaît — y compris les parts orphelines (titulaire
        // anonymisé) : un partage réécrit remplace l'ancien en entier.
        foreach ($existing as $id => $share) {
            if (!isset($kept[$id])) {
                $expense->removeShare($share);
            }
        }
    }

    /**
     * 50/50 entre les parents actifs (§3.1.A). Une famille à parent unique voit ce parent porter
     * 100 % : c'est un comportement défini, pas une erreur (§4.6).
     *
     * @return list<array{0: Membership, 1: ShareType, 2: int}>
     */
    private function defaultShares(Family $family): array
    {
        $parents = $this->activeParents($family);
        if ([] === $parents) {
            throw new ExpenseInputException('expense.error.no_parent');
        }

        $percent = intdiv(100, \count($parents));
        $shares = [];
        foreach ($parents as $index => $parent) {
            $shares[] = [
                $parent,
                ShareType::Percent,
                // Le premier parent absorbe le reliquat quand 100 n'est pas divisible par le nombre
                // de parents : la somme doit valoir 100 exactement (§3.1, règle 2).
                0 === $index ? 100 - $percent * (\count($parents) - 1) : $percent,
            ];
        }

        return $shares;
    }

    /**
     * @param list<ShareInput> $inputs
     *
     * @return list<array{0: Membership, 1: ShareType, 2: int}>
     */
    private function validatedShares(Expense $expense, Family $family, array $inputs): array
    {
        $types = [];
        foreach ($inputs as $input) {
            $type = ShareType::tryFrom((string) $input->shareType);
            if (null === $type) {
                throw new ExpenseInputException('expense.error.share_type_invalid');
            }
            $types[$type->value] = true;
        }
        if (\count($types) > 1) {
            throw new ExpenseInputException('expense.error.share_type_mixed');
        }

        $type = ShareType::from((string) array_key_first($types));
        $shares = [];
        $seen = [];
        $sum = 0;

        foreach ($inputs as $input) {
            $membership = $this->activeMember($family, (string) $input->membershipId);
            if (null === $membership || MembershipRole::Parent !== $membership->getRole()) {
                // Une part au nom d'un beau-parent ou d'un enfant est refusée : le solde « qui doit
                // quoi » est inter-parents (cdc §2.7), il n'aurait nulle part où la porter.
                throw new ExpenseInputException('expense.error.share_member_invalid');
            }
            $id = (string) $membership->getId();
            if (isset($seen[$id])) {
                throw new ExpenseInputException('expense.error.share_duplicate');
            }
            $seen[$id] = true;

            $value = $input->value;
            if (null === $value || $value < 0) {
                throw new ExpenseInputException('expense.error.share_value_invalid');
            }
            if (ShareType::Percent === $type && $value > 100) {
                throw new ExpenseInputException('expense.error.share_value_invalid');
            }
            $sum += $value;

            $shares[] = [$membership, $type, $value];
        }

        $this->assertSum($type, $sum, $expense->getAmountCents());

        return $shares;
    }

    /** Somme attendue : 100 en pourcentage, le montant exact en centimes (tolérance nulle, §3.1). */
    private function assertSum(ShareType $type, int $sum, int $amountCents): void
    {
        if (ShareType::Percent === $type && 100 !== $sum) {
            throw new ExpenseInputException('expense.error.share_percent_sum');
        }
        if (ShareType::Amount === $type && $sum !== $amountCents) {
            throw new ExpenseInputException('expense.error.share_amount_sum');
        }
    }

    /**
     * Après un changement de montant sans nouveau partage : des parts en centimes qui ne somment
     * plus au total doivent être refusées (§2 US-DEP-4, cas d'erreur). Les parts en pourcentage,
     * elles, suivent le montant par construction.
     */
    private function assertExistingSharesStillConsistent(Expense $expense): void
    {
        $sum = 0;
        $type = null;
        foreach ($expense->getShares() as $share) {
            $type = $share->getShareType();
            $sum += $share->getValue();
        }
        if (null === $type) {
            return;
        }

        $this->assertSum($type, $sum, $expense->getAmountCents());
    }

    /** @return list<Membership> */
    private function activeParents(Family $family): array
    {
        return array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn (Membership $m): bool => MembershipRole::Parent === $m->getRole(),
        ));
    }

    private function activeMember(Family $family, string $membershipId): ?Membership
    {
        if (!Uuid::isValid($membershipId)) {
            return null;
        }

        foreach ($this->memberships->findActiveByFamily($family) as $member) {
            if ((string) $member->getId() === $membershipId) {
                return $member;
            }
        }

        return null;
    }
}
