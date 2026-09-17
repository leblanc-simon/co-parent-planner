<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Rappel de dépense envoyé trop tôt (Lot 10 §4.9, cooldown 72 h — décision PO du 2026-09-18) → 429.
 *
 * Le cooldown protège le débiteur du harcèlement par e-mail : c'est une règle métier, pas une limite
 * de débit technique, d'où le `Retry-After` exprimé en secondes restantes plutôt qu'une fenêtre fixe.
 */
final class ExpenseReminderTooSoonException extends AbstractProblemException
{
    public const string TYPE = '/errors/reminder-too-soon';

    public function __construct(int $retryAfterSeconds, int $hours)
    {
        parent::__construct(
            429,
            self::TYPE,
            'expense.error.reminder_too_soon',
            ['hours' => $hours],
            ['Retry-After' => (string) max(1, $retryAfterSeconds)],
        );
    }
}
