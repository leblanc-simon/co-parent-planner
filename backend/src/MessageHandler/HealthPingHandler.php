<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\HealthPing;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Trace la bonne santé du scheduler (job de démonstration Lot 0). */
#[AsMessageHandler]
final class HealthPingHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(HealthPing $message): void
    {
        $this->logger->info('Scheduler health ping OK.');
    }
}
