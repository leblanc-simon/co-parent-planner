<?php

declare(strict_types=1);

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\Membership;
use App\Enum\NotificationType;
use App\Exception\ExpenseInputException;
use App\Exception\ExpenseReminderTooSoonException;
use App\Service\Notification\NotificationEmitter;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rappel « cette dépense n'est pas réglée » (US-DEP-2, Lot 10 §4.9).
 *
 * Deux règles portent tout le service :
 *
 *  - **le destinataire est le débiteur de cette dépense**, c'est-à-dire tout parent porteur d'une
 *    part non nulle, payeur exclu — celui qui a avancé l'argent n'a rien à se faire rappeler. On ne
 *    passe pas par le solde familial : un rappel désigne une ligne, pas un solde net, et deux
 *    dépenses croisées peuvent laisser un débiteur net qui n'est pas celui de la ligne visée ;
 *  - **un cooldown de 72 h par dépense** (décision PO du 2026-09-18) : sans lui, le bouton devient
 *    un outil de harcèlement par e-mail, ce que ni le quiet-hours ni l'anti-spam de masse du Lot 7
 *    n'interceptent (ils bornent le volume global, pas la répétition sur une même ligne).
 *
 * L'envoi est asynchrone (Messenger) : ce service confirme la **mise en file**, jamais la remise.
 */
final class ExpenseReminder
{
    public function __construct(
        private readonly ShareCalculator $shares,
        private readonly NotificationEmitter $notifier,
        private readonly ClockInterface $clock,
        #[Autowire('%env(int:EXPENSE_REMINDER_COOLDOWN_HOURS)%')]
        private readonly int $cooldownHours = 72,
    ) {
    }

    /**
     * @return list<Membership> destinataires effectivement visés (préférence e-mail appliquée en aval)
     *
     * @throws ExpenseInputException           la dépense est déjà réglée, ou personne ne la doit
     * @throws ExpenseReminderTooSoonException un rappel a déjà été envoyé dans la fenêtre de cooldown
     */
    public function send(Expense $expense): array
    {
        if ($expense->isSettled()) {
            throw new ExpenseInputException('expense.error.reminder_settled');
        }

        $this->assertCooldownElapsed($expense);

        $debtors = $this->debtors($expense);
        if ([] === $debtors) {
            // Une dépense intégralement à la charge de son payeur ne crée aucune dette : rappeler
            // quoi, et à qui ? Refuser explicitement vaut mieux qu'un succès sans destinataire.
            throw new ExpenseInputException('expense.error.reminder_no_debtor');
        }

        foreach ($debtors as $debtor) {
            $this->notifier->emitTo(
                NotificationType::ExpenseReminder,
                $expense->getFamily(),
                $debtor,
                (string) $expense->getId(),
                ['title' => $expense->getTitle()],
            );
        }

        $expense->setReminderSentAt($this->clock->now());

        return $debtors;
    }

    /**
     * Instant à partir duquel un nouveau rappel est accepté, ou `null` si c'est déjà le cas.
     * Exposé au contrat pour que l'UI puisse désactiver le bouton au lieu d'essuyer un 429.
     */
    public function availableAt(Expense $expense): ?\DateTimeImmutable
    {
        $last = $expense->getReminderSentAt();
        if (null === $last) {
            return null;
        }

        $nextAllowed = $last->modify(\sprintf('+%d hours', $this->cooldownHours));

        return $this->clock->now() < $nextAllowed ? $nextAllowed : null;
    }

    private function assertCooldownElapsed(Expense $expense): void
    {
        $nextAllowed = $this->availableAt($expense);
        if (null === $nextAllowed) {
            return;
        }

        throw new ExpenseReminderTooSoonException($nextAllowed->getTimestamp() - $this->clock->now()->getTimestamp(), $this->cooldownHours);
    }

    /**
     * Parents devant une part non nulle sur cette dépense, payeur exclu.
     *
     * @return list<Membership>
     */
    private function debtors(Expense $expense): array
    {
        $due = $this->shares->effective($expense);
        $payerId = (string) $expense->getPayerMembership()?->getId();

        $debtors = [];
        foreach ($expense->getShares() as $share) {
            $membership = $share->getMembership();
            if (null === $membership) {
                continue;
            }
            $id = (string) $membership->getId();
            if ($id === $payerId || 0 === ($due[$id] ?? 0)) {
                continue;
            }
            $debtors[] = $membership;
        }

        return $debtors;
    }
}
