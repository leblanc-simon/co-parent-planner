<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeExpiredInvitations;
use App\Repository\InvitationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Marque `expired` les invitations `pending` échues (§3.3.E). Idempotent, journalisé. */
#[AsMessageHandler]
final class PurgeExpiredInvitationsHandler
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeExpiredInvitations $message): void
    {
        $count = $this->invitations->markExpired();
        if ($count > 0) {
            $this->logger->info('Invitations expirées marquées.', ['count' => $count]);
        }
    }
}
