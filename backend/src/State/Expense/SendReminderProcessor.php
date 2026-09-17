<?php

declare(strict_types=1);

namespace App\State\Expense;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Expense\ExpenseResource;
use App\Entity\Membership;
use App\Enum\AuditAction;
use App\Security\Auth\AuditLogger;
use App\Security\Expense\ExpenseAccess;
use App\Service\Expense\ExpensePresenter;
use App\Service\Expense\ExpenseReminder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-DEP-2 — envoie un rappel au(x) parent(s) débiteur(s) d'une dépense non réglée (expenses:edit).
 *
 * La réponse renvoie la dépense mise à jour, `reminderAvailableAt` compris : le client sait alors
 * désactiver son bouton jusqu'à la fin du cooldown au lieu de découvrir un 429 au clic suivant.
 *
 * Aucun événement Mercure n'est publié : un rappel n'altère pas les comptes, et pousser un
 * rafraîchissement à toute la famille pour un e-mail personnel serait aussi bruyant qu'indiscret.
 *
 * @implements ProcessorInterface<mixed, ExpenseResource>
 */
final class SendReminderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ExpenseAccess $access,
        private readonly ExpenseReminder $reminder,
        private readonly ExpensePresenter $presenter,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExpenseResource
    {
        $ctx = $this->access->write();
        $expense = $this->access->load($uriVariables['id'] ?? null, $ctx->family);

        $debtors = $this->reminder->send($expense);

        $this->audit->log(
            AuditAction::ExpenseReminderSent,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['debtors' => implode(',', array_map(
                static fn (Membership $m): string => (string) $m->getId(),
                $debtors,
            ))],
            entityType: 'Expense',
            entityId: (string) $expense->getId(),
        ); // flush (commit) — persiste aussi reminderSentAt

        return $this->presenter->one($expense);
    }
}
