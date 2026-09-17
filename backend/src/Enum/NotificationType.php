<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type précis de notification (Lot 7). Porte sa catégorie (pour le filtrage préférence + Voter) et
 * sert de clé de rendu (sujet/corps i18n `email.notif.<type>.*`).
 */
enum NotificationType: string
{
    // Échanges (Lot 6)
    case ExchangeMessage = 'exchange_message';
    case ExchangeOpened = 'exchange_opened';
    case ExchangeDecided = 'exchange_decided';
    case ExchangeClosed = 'exchange_closed';
    case DecisionReminder = 'decision_reminder';
    case DecisionOverdue = 'decision_overdue';

    // Agenda (Lot 4)
    case AgendaEventCreated = 'agenda_event_created';
    case AgendaEventUpdated = 'agenda_event_updated';
    case AgendaEventDeleted = 'agenda_event_deleted';
    case AgendaReminder = 'agenda_reminder';

    // Garde (Lot 3)
    case CustodyChanged = 'custody_changed';

    // Dépenses (Lot 10, v2)
    case ExpenseAdded = 'expense_added';
    case ExpenseSettled = 'expense_settled';
    case ExpenseReminder = 'expense_reminder';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::ExchangeMessage, self::ExchangeOpened, self::ExchangeDecided,
            self::ExchangeClosed, self::DecisionReminder, self::DecisionOverdue => NotificationCategory::Exchanges,
            self::AgendaEventCreated, self::AgendaEventUpdated,
            self::AgendaEventDeleted, self::AgendaReminder => NotificationCategory::Agenda,
            self::CustodyChanged => NotificationCategory::Custody,
            self::ExpenseAdded, self::ExpenseSettled, self::ExpenseReminder => NotificationCategory::Expenses,
        };
    }

    /** Domaine de droit à vérifier sur le destinataire (Voter). */
    public function rightDomain(): RightDomain
    {
        return match ($this->category()) {
            NotificationCategory::Custody => RightDomain::Custody,
            NotificationCategory::Agenda => RightDomain::Agenda,
            NotificationCategory::Exchanges => RightDomain::Exchanges,
            NotificationCategory::Expenses => RightDomain::Expenses,
            default => RightDomain::Agenda,
        };
    }
}
