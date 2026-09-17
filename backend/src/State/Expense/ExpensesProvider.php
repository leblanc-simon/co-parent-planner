<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Expense\ExpenseResource;
use App\Entity\Child;
use App\Entity\Family;
use App\Enum\ExpenseCategory;
use App\Exception\ExpenseInputException;
use App\Repository\ChildRepository;
use App\Repository\ExpenseRepository;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\ExpensePresenter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-DEP-3 — liste les dépenses de la famille active (expenses:view), triées par date décroissante,
 * filtrables par catégorie, enfant et état.
 *
 * Un filtre invalide répond **422** plutôt qu'une liste vide : « aucune dépense » et « votre filtre
 * ne veut rien dire » se ressemblent à l'écran, et seul le second est corrigible par l'utilisateur.
 * Un `childId` d'une autre famille est traité comme invalide — jamais comme une liste vide, qui
 * confirmerait au passage que l'identifiant existe (§4.5).
 *
 * @implements ProviderInterface<ExpenseResource>
 */
final class ExpensesProvider implements ProviderInterface
{
    public function __construct(
        private readonly ExpenseAccess $access,
        private readonly ExpenseRepository $expenses,
        private readonly ChildRepository $children,
        private readonly ExpensePresenter $presenter,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<ExpenseResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->read();

        return $this->presenter->many($this->expenses->findByFamily(
            $ctx->family,
            $this->category(),
            $this->child($ctx->family),
            $this->settled(),
        ));
    }

    private function category(): ?ExpenseCategory
    {
        $raw = $this->query('category');
        if (null === $raw) {
            return null;
        }

        return ExpenseCategory::tryFrom($raw) ?? throw new ExpenseInputException('expense.error.category_invalid');
    }

    private function child(Family $family): ?Child
    {
        $raw = $this->query('childId');
        if (null === $raw) {
            return null;
        }
        if (!Uuid::isValid($raw)) {
            throw new ExpenseInputException('expense.error.child_invalid');
        }

        foreach ($this->children->findByFamily($family) as $child) {
            if ((string) $child->getId() === $raw) {
                return $child;
            }
        }

        throw new ExpenseInputException('expense.error.child_invalid');
    }

    private function settled(): ?bool
    {
        $raw = $this->query('settled');

        return match ($raw) {
            null => null,
            'true', '1' => true,
            'false', '0' => false,
            default => throw new ExpenseInputException('expense.error.settled_invalid'),
        };
    }

    private function query(string $name): ?string
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get($name);

        return \is_string($raw) && '' !== $raw ? $raw : null;
    }
}
