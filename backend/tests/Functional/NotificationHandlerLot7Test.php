<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\NotificationStatus;
use App\Message\SendNotification;
use App\MessageHandler\SendNotificationHandler;
use App\Repository\NotificationDispatchRepository;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Lot 7 — le worker d'envoi rend le template et compose l'e-mail sans lever, et marque l'idempotence
 * `sent` (un rejeu de même clé n'envoie pas deux fois).
 */
final class NotificationHandlerLot7Test extends ApiAuthTestCase
{
    public function testHandlerComposesEmailAndIsIdempotent(): void
    {
        self::createClient();
        $user = $this->createActiveUser('handler@example.com');

        $handler = self::getContainer()->get(SendNotificationHandler::class);
        self::assertInstanceOf(SendNotificationHandler::class, $handler);

        $message = new SendNotification(
            'exchange_message:fact-1:'.$user->getId(),
            (string) $user->getId(),
            null,
            'exchange_message',
            ['title' => 'Organisation vacances', 'exchangeId' => 'abc'],
        );

        $handler($message); // ne doit pas lever (rendu + composition OK)

        // Un e-mail a été remis au transport (SendEmailMessage), et l'idempotence est marquée 'sent'.
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $emails = array_filter($transport->getSent(), static fn ($e): bool => $e->getMessage() instanceof SendEmailMessage);
        self::assertNotEmpty($emails);

        $dispatches = self::getContainer()->get(NotificationDispatchRepository::class);
        self::assertInstanceOf(NotificationDispatchRepository::class, $dispatches);
        $dispatch = $dispatches->findOneByKey('exchange_message:fact-1:'.$user->getId());
        self::assertNotNull($dispatch);
        self::assertSame(NotificationStatus::Sent, $dispatch->getStatus());

        // Rejeu : idempotent (pas de second envoi).
        $before = \count(array_filter($transport->getSent(), static fn ($e): bool => $e->getMessage() instanceof SendEmailMessage));
        $handler($message);
        $after = \count(array_filter($transport->getSent(), static fn ($e): bool => $e->getMessage() instanceof SendEmailMessage));
        self::assertSame($before, $after);
    }
}
