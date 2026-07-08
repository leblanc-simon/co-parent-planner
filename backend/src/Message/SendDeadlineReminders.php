<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche le balayage des rappels d'échéance des décisions (Lot 7 §3.9). Planifié par le Scheduler ;
 * idempotent (la table `NotificationDispatch` empêche les envois répétés à chaque exécution).
 */
final class SendDeadlineReminders
{
}
