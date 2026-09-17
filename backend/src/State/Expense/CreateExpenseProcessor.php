<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Expense\CreateExpenseRequest;
use App\Dto\Expense\ExpenseResource;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Security\Auth\AuditLogger;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\ExpensePresenter;
use App\Service\Expense\ExpensePublisher;
use App\Service\Expense\ExpenseWriter;
use App\Service\Notification\NotificationEmitter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-DEP-1 — crée une dépense et matérialise ses parts (expenses:edit).
 *
 * L'écriture est **transactionnelle par construction** : la dépense, ses parts et ses liaisons
 * enfants sont persistées en cascade dans un seul flush, et toute violation de validation lève avant
 * lui — il ne reste donc jamais de dépense sans partage.
 *
 * L'idempotence des rejeux d'outbox (`Idempotency-Key`) est assurée en amont par le middleware
 * transverse {@see \App\EventSubscriber\IdempotencySubscriber} : elle n'a pas à être réimplémentée
 * ici, contrairement au Lot 4 qui précédait ce middleware.
 *
 * @implements ProcessorInterface<CreateExpenseRequest, ExpenseResource>
 */
final class CreateExpenseProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
        \assert($data instanceof CreateExpenseRequest);

        $ctx = $this->access->write();
        $expense = $this->writer->build($ctx->family, $ctx->membership, $data);
        $this->em->persist($expense);

        $this->audit->log(
            AuditAction::ExpenseCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Expense',
            entityId: (string) $expense->getId(),
        ); // flush (commit)

        // Après commit : diffusion temps réel puis notification, toutes deux best-effort (§4.7).
        $this->publisher->publish($ctx->family);
        $this->notifier->emit(NotificationType::ExpenseAdded, $ctx->family, $ctx->membership, (string) $expense->getId(), [
            'title' => $expense->getTitle(),
        ]);

        return $this->presenter->one($expense);
    }
}
