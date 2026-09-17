<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Expense\ExpenseResource;
use App\Dto\Expense\UpdateExpenseRequest;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Security\Auth\AuditLogger;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\ExpensePresenter;
use App\Service\Expense\ExpensePublisher;
use App\Service\Expense\ExpenseWriter;
use App\Service\Notification\NotificationEmitter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-DEP-2 / US-DEP-4 — modifie une dépense ou la marque réglée (expenses:edit).
 *
 * **Marquer réglé est idempotent** : repasser `settled: true` sur une dépense déjà réglée répond 200
 * sans ré-émettre de notification. C'est la règle qui compte le plus ici — deux parents qui règlent
 * la même dépense en même temps, ou un client qui rejoue sa requête, ne doivent pas produire deux
 * e-mails « dépense réglée » pour un seul fait.
 *
 * Une dépense réglée reste modifiable (§2 US-DEP-4, cas particulier) : le règlement n'est pas un gel,
 * et `settled` ne retombe à `false` que si le client le demande explicitement.
 *
 * @implements ProcessorInterface<UpdateExpenseRequest, ExpenseResource>
 */
final class UpdateExpenseProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ExpenseAccess $access,
        private readonly ExpenseWriter $writer,
        private readonly ExpensePresenter $presenter,
        private readonly ExpensePublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExpenseResource
    {
        \assert($data instanceof UpdateExpenseRequest);

        $ctx = $this->access->write();
        $expense = $this->access->load($uriVariables['id'] ?? null, $ctx->family);

        $wasSettled = $expense->isSettled();
        $this->writer->update($expense, $ctx->family, $data);
        $justSettled = !$wasSettled && $expense->isSettled();

        $this->audit->log(
            $justSettled ? AuditAction::ExpenseSettled : AuditAction::ExpenseUpdated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Expense',
            entityId: (string) $expense->getId(),
        ); // flush (commit)

        $this->publisher->publish($ctx->family);
        if ($justSettled) {
            $this->notifier->emit(NotificationType::ExpenseSettled, $ctx->family, $ctx->membership, (string) $expense->getId(), [
                'title' => $expense->getTitle(),
            ]);
        }

        return $this->presenter->one($expense);
    }
}
