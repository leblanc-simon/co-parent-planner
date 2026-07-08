<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Exchange;
use App\Enum\NotificationType;
use App\Message\SendDeadlineReminders;
use App\Repository\ExchangeRepository;
use App\Service\Notification\NotificationEmitter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Balaye les décisions ouvertes à échéance (Lot 7 §3.9, US-NOTIF-6) et émet un rappel « échéance
 * demain » (< 24 h avant la fin de journée d'échéance) puis « en retard » (échéance dépassée). La
 * déduplication est portée par la clé `(type:exchangeId:kind × destinataire)` de `NotificationDispatch`
 * : le job peut tourner souvent sans renvoyer. Une décision tranchée sort du balayage (statut ≠ open).
 */
#[AsMessageHandler]
final class SendDeadlineRemindersHandler
{
    public function __construct(
        private readonly ExchangeRepository $exchanges,
        private readonly NotificationEmitter $notifier,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SendDeadlineReminders $message): void
    {
        $now = $this->clock->now();

        foreach ($this->exchanges->findOpenDecisionsWithDeadline() as $exchange) {
            $deadline = $exchange->getDeadline();
            if (null === $deadline) {
                continue;
            }
            $tz = new \DateTimeZone($exchange->getFamily()->getTimezone());
            $endOfDeadline = new \DateTimeImmutable($deadline->format('Y-m-d').' 23:59:59.999999', $tz);

            [$type, $kind] = match (true) {
                $now > $endOfDeadline => [NotificationType::DecisionOverdue, 'overdue'],
                $now >= $endOfDeadline->modify('-1 day') => [NotificationType::DecisionReminder, 'before_24h'],
                default => [null, null],
            };
            if (null === $type || null === $kind) {
                continue;
            }

            $this->notify($exchange, $type, $kind);
        }
    }

    private function notify(Exchange $exchange, NotificationType $type, string $kind): void
    {
        $this->notifier->emit(
            $type,
            $exchange->getFamily(),
            null, // pas d'auteur : rappel adressé à tous les participants habilités
            (string) $exchange->getId().':'.$kind,
            ['exchangeId' => (string) $exchange->getId(), 'title' => $exchange->getTitle()],
        );
    }
}
