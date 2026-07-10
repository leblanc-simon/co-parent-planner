<?php

declare(strict_types=1);

namespace App;

use App\Enum\RgpdMode;
use App\Message\ExtendCustodyHorizon;
use App\Message\HealthPing;
use App\Message\PurgeExpiredInvitations;
use App\Message\RefreshSchoolHolidays;
use App\Message\RunRgpdPurge;
use App\Message\SendDeadlineReminders;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Planification principale (cdc §4.2). Consommée par `messenger:consume scheduler_default`.
 * Au Lot 0 : un job de santé. Les vraies tâches (régénération d'horizon de garde, rafraîchissement
 * vacances, rappels d'échéance, purges RGPD) sont ajoutées à leurs lots.
 */
#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // rejoue les exécutions manquées
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('1 hour', new HealthPing()))
            ->add(RecurringMessage::every('1 hour', new PurgeExpiredInvitations()))
            // Garde (Lot 3) : horizon glissant étendu quotidiennement ; vacances rafraîchies mensuellement.
            ->add(RecurringMessage::every('1 day', new ExtendCustodyHorizon()))
            ->add(RecurringMessage::every('1 month', new RefreshSchoolHolidays()))
            // Notifications (Lot 7) : rappels d'échéance des décisions (dédup idempotente).
            ->add(RecurringMessage::every('15 minutes', new SendDeadlineReminders()))
            // RGPD (Lot 8) : purge planifiée quotidienne (live), idempotente et journalisée (§3.4).
            ->add(RecurringMessage::every('1 day', new RunRgpdPurge(RgpdMode::Live->value)));
    }
}
