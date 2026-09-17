<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enum\AuditAction;
use App\Security\Auth\AuditLogger;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\ExpensePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-DEP-5 — supprime une dépense saisie par erreur (expenses:edit). Parts et liaisons enfants
 * partent en cascade ; le solde se recalcule de lui-même, n'étant jamais matérialisé.
 *
 * Extension du cdc §6.2 **validée par le porteur produit le 2026-09-18** : sans elle, une erreur de
 * saisie resterait au total engagé pour toujours, faute de pouvoir la retirer autrement qu'en la
 * déclarant réglée — ce qui mentirait sur les comptes.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteExpenseProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExpenseAccess $access,
        private readonly ExpensePublisher $publisher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->write();
        $expense = $this->access->load($uriVariables['id'] ?? null, $ctx->family);
        $id = (string) $expense->getId();

        $this->em->remove($expense);

        $this->audit->log(
            AuditAction::ExpenseDeleted,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Expense',
            entityId: $id,
        ); // flush (commit)

        $this->publisher->publish($ctx->family);

        return null;
    }
}
