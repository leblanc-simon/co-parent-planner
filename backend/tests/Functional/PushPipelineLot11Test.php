<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\DeviceToken;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\NotificationPref;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\DevicePlatform;
use App\Enum\DeviceTokenStatus;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use App\Enum\NotificationType;
use App\Enum\RgpdMode;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Message\SendNotification;
use App\Message\SendPushNotification;
use App\MessageHandler\SendPushNotificationHandler;
use App\Repository\DeviceTokenRepository;
use App\Rgpd\Anonymizer;
use App\Rgpd\Export\UserDataExporter;
use App\Rgpd\Purger;
use App\Security\Encryption\BlindIndexHasher;
use App\Service\Notification\NotificationEmitter;
use App\Service\Notification\Push\PushDeliveryException;
use App\Service\Notification\Push\PushResult;
use App\Service\Notification\Push\PushSender;
use App\Tests\Support\FakePushSender;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Lot 11 — canal push dans le pipeline du Lot 7 : opt-in par catégorie, découplage e-mail/push,
 * fan-out multi-appareils, invalidation sur refus définitif seulement, idempotence par appareil au
 * rejeu, quiet-hours, contenu sans champ 🔒, exclusion des enfants des dépenses, et cycle RGPD des
 * jetons (effacement, purge, export). Passerelle : {@see FakePushSender}, aucun appel réseau.
 */
final class PushPipelineLot11Test extends ApiAuthTestCase
{
    private function fake(): FakePushSender
    {
        // Résolu par l'interface : le fake n'existe que dans le conteneur `test`.
        $fake = self::getContainer()->get(PushSender::class);
        self::assertInstanceOf(FakePushSender::class, $fake);

        return $fake;
    }

    private function handler(): SendPushNotificationHandler
    {
        $handler = self::getContainer()->get(SendPushNotificationHandler::class);
        self::assertInstanceOf(SendPushNotificationHandler::class, $handler);

        return $handler;
    }

    private function emitter(): NotificationEmitter
    {
        $emitter = self::getContainer()->get(NotificationEmitter::class);
        self::assertInstanceOf(NotificationEmitter::class, $emitter);

        return $emitter;
    }

    /** @return array{0: Family, 1: Membership, 2: Membership} famille, auteur, destinataire */
    private function family(MembershipRole $recipientRole = MembershipRole::Parent, bool $expenses = false): array
    {
        self::createClient();
        $family = (new Family())->setName('Famille Push')->setType(FamilyType::Separated)->setExpensesEnabled($expenses);
        $this->em()->persist($family);

        $author = (new Membership())->setUser($this->createActiveUser('push-author-'.uniqid().'@example.com'))
            ->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $recipient = (new Membership())->setUser($this->createActiveUser('push-recipient-'.uniqid().'@example.com'))
            ->setFamily($family)->setRole($recipientRole)->setStatus(MembershipStatus::Active);
        foreach (RightDomain::cases() as $domain) {
            $recipient->addRight(new Right($domain, RightLevel::Edit));
        }
        $this->em()->persist($author);
        $this->em()->persist($recipient);
        $this->em()->flush();

        return [$family, $author, $recipient];
    }

    private function device(User $user, string $token, DevicePlatform $platform = DevicePlatform::Android): DeviceToken
    {
        $hasher = self::getContainer()->get(BlindIndexHasher::class);
        self::assertInstanceOf(BlindIndexHasher::class, $hasher);
        $device = (new DeviceToken($user, $platform, $token, $hasher->hashExact($token)))->seen(Clock::get()->now());
        $this->em()->persist($device);
        $this->em()->flush();

        return $device;
    }

    private function pref(User $user, NotificationCategory $category, NotificationChannel $channel, bool $enabled): void
    {
        $this->em()->persist((new NotificationPref())->setUser($user)->setCategory($category)->setChannel($channel)->setEnabled($enabled));
        $this->em()->flush();
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    /** @return list<SendPushNotification> */
    private function pushMessages(): array
    {
        $out = [];
        foreach ($this->transport()->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof SendPushNotification) {
                $out[] = $envelope->getMessage();
            }
        }

        return $out;
    }

    /** @return list<SendNotification> */
    private function emailMessages(): array
    {
        $out = [];
        foreach ($this->transport()->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof SendNotification) {
                $out[] = $envelope->getMessage();
            }
        }

        return $out;
    }

    private function deviceStatus(DeviceToken $device): DeviceTokenStatus
    {
        $this->em()->clear();
        $reloaded = self::getContainer()->get(DeviceTokenRepository::class)->find($device->getId());
        self::assertInstanceOf(DeviceToken::class, $reloaded);

        return $reloaded->getStatus();
    }

    private function message(Membership $recipient, Family $family, string $fact = 'fact-1', string $type = 'exchange_message'): SendPushNotification
    {
        return new SendPushNotification(
            $type.':'.$fact,
            (string) $recipient->getUser()->getId(),
            (string) $family->getId(),
            $type,
            ['exchangeId' => 'ex-42', 'title' => 'Vacances de Noël'],
        );
    }

    // --- Émission : opt-in, découplage ---------------------------------------------------------

    public function testNoPushWithoutExplicitOptIn(): void
    {
        [$family, $author, $recipient] = $this->family();
        $this->device($recipient->getUser(), 'jeton-sans-optin');

        $this->emitter()->emit(NotificationType::ExchangeOpened, $family, $author, 'f1', ['exchangeId' => 'x', 'title' => 'T']);

        self::assertSame([], $this->pushMessages());
        self::assertCount(1, $this->emailMessages()); // l'e-mail, lui, reste opt-out
    }

    public function testNoPushWithoutRegisteredDevice(): void
    {
        [$family, $author, $recipient] = $this->family();
        $this->pref($recipient->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, true);

        $this->emitter()->emit(NotificationType::ExchangeOpened, $family, $author, 'f1', ['exchangeId' => 'x', 'title' => 'T']);

        self::assertSame([], $this->pushMessages()); // pas d'appareil = pas de permission OS
    }

    public function testPushAndEmailAreIndependent(): void
    {
        [$family, $author, $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->device($user, 'jeton-decouple');
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Email, false);
        $this->pref($user, NotificationCategory::Agenda, NotificationChannel::Push, false);

        $this->emitter()->emit(NotificationType::ExchangeOpened, $family, $author, 'f1', ['exchangeId' => 'x', 'title' => 'T']);
        $this->emitter()->emit(NotificationType::AgendaEventCreated, $family, $author, 'f2', ['title' => 'Piscine']);

        $pushes = $this->pushMessages();
        self::assertCount(1, $pushes);
        self::assertSame('exchange_opened', $pushes[0]->type);
        self::assertSame('exchange_opened:f1', $pushes[0]->eventKey);
        $emails = $this->emailMessages();
        self::assertCount(1, $emails); // agenda par e-mail seulement ; échanges par push seulement
        self::assertSame('agenda_event_created', $emails[0]->type);
    }

    public function testAuthorIsNotPushedToItself(): void
    {
        [$family, $author] = $this->family();
        $this->device($author->getUser(), 'jeton-auteur');
        $this->pref($author->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, true);

        $this->emitter()->emit(NotificationType::ExchangeOpened, $family, $author, 'f1', ['title' => 'T']);

        self::assertSame([], $this->pushMessages());
    }

    public function testChildIsNeverPushedExpenses(): void
    {
        [$family, $author, $child] = $this->family(MembershipRole::Child, expenses: true);
        $this->device($child->getUser(), 'jeton-enfant');
        $this->pref($child->getUser(), NotificationCategory::Expenses, NotificationChannel::Push, true);

        $this->emitter()->emit(NotificationType::ExpenseAdded, $family, $author, 'f1', ['title' => 'Cantine']);

        self::assertSame([], $this->pushMessages());
    }

    public function testDisabledGatewayEnqueuesNothing(): void
    {
        [$family, $author, $recipient] = $this->family();
        $this->fake()->enabled = false;
        $this->device($recipient->getUser(), 'jeton-passerelle-eteinte');
        $this->pref($recipient->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, true);

        $this->emitter()->emit(NotificationType::ExchangeOpened, $family, $author, 'f1', ['title' => 'T']);

        self::assertSame([], $this->pushMessages());
    }

    // --- Envoi : fan-out, idempotence, nettoyage -----------------------------------------------

    public function testFansOutToEveryActiveDeviceOnceEvenWhenReplayed(): void
    {
        [$family, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->device($user, 'jeton-ios', DevicePlatform::Ios);
        $this->device($user, 'jeton-android');
        $this->device($user, 'jeton-revoque')->revoke(Clock::get()->now());
        $this->em()->flush();

        $message = $this->message($recipient, $family);
        $this->handler()($message);
        $this->handler()($message); // rejeu (retry Messenger, second worker)

        $sent = $this->fake()->sentTokens();
        sort($sent);
        self::assertSame(['jeton-android', 'jeton-ios'], $sent);
        self::assertSame(1, $this->em()->getRepository(AuditLog::class)->count(['action' => AuditAction::NotificationSent]));
    }

    public function testInvalidTokenIsRetiredWithoutBlockingOthers(): void
    {
        [$family, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->device($user, 'jeton-a');
        $dead = $this->device($user, 'jeton-mort');
        $this->device($user, 'jeton-b', DevicePlatform::Ios);
        $this->fake()->willAnswer('jeton-mort', PushResult::InvalidToken);

        $this->handler()($this->message($recipient, $family)); // ne lève pas : rien à rejouer

        $sent = $this->fake()->sentTokens();
        sort($sent);
        self::assertSame(['jeton-a', 'jeton-b'], $sent);
        self::assertSame(DeviceTokenStatus::Invalid, $this->deviceStatus($dead));
        self::assertSame(1, $this->em()->getRepository(AuditLog::class)->count(['action' => AuditAction::DeviceInvalidated]));
    }

    public function testTransientFailureKeepsTokenAndReplayServesOnlyTheMissedDevice(): void
    {
        [$family, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->device($user, 'jeton-ok');
        $flaky = $this->device($user, 'jeton-instable');
        $this->fake()->willAnswer('jeton-instable', PushResult::Retryable);
        $message = $this->message($recipient, $family);

        try {
            $this->handler()($message);
            self::fail('Un échec passager doit lever pour que Messenger rejoue.');
        } catch (PushDeliveryException) {
        }
        self::assertSame(DeviceTokenStatus::Active, $this->deviceStatus($flaky)); // jamais invalidé

        $this->fake()->willAnswer('jeton-instable', PushResult::Delivered);
        $this->handler()($message);

        self::assertSame(['jeton-ok', 'jeton-instable'], $this->fake()->sentTokens());
    }

    public function testGatewayAuthFailureNeverInvalidatesUserTokens(): void
    {
        [$family, , $recipient] = $this->family();
        $this->pref($recipient->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $device = $this->device($recipient->getUser(), 'jeton-auth');
        $this->fake()->willAnswer('jeton-auth', PushResult::AuthFailure);

        $this->expectException(PushDeliveryException::class);
        try {
            $this->handler()($this->message($recipient, $family));
        } finally {
            self::assertSame(DeviceTokenStatus::Active, $this->deviceStatus($device));
        }
    }

    public function testOptOutAfterEmissionIsHonouredAtSendTime(): void
    {
        [$family, , $recipient] = $this->family();
        $this->pref($recipient->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, false);
        $this->device($recipient->getUser(), 'jeton-opt-out');

        $this->handler()($this->message($recipient, $family));

        self::assertSame([], $this->fake()->sent);
    }

    public function testQuietHoursDeferThePush(): void
    {
        [$family, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->pref($user, NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->device($user, 'jeton-nuit');
        // Plage de silence qui contient « maintenant », quelle que soit l'heure du test.
        $local = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'));
        $user->setQuietHoursStart($local->modify('-1 hour')->format('H:i'))->setQuietHoursEnd($local->modify('+1 hour')->format('H:i'));
        $this->em()->flush();

        $this->handler()($this->message($recipient, $family));

        self::assertSame([], $this->fake()->sent);
        $deferred = null;
        foreach ($this->transport()->getSent() as $envelope) {
            if ($envelope->getMessage() instanceof SendPushNotification) {
                $deferred = $envelope;
            }
        }
        self::assertNotNull($deferred);
        $message = $deferred->getMessage();
        self::assertInstanceOf(SendPushNotification::class, $message);
        self::assertTrue($message->deferred);
        self::assertNotNull($deferred->last(DelayStamp::class));

        // Au terme du report, le message déjà différé part sans être re-reporté.
        $this->handler()($message);
        self::assertSame(['jeton-nuit'], $this->fake()->sentTokens());
    }

    // --- Contenu ------------------------------------------------------------------------------

    public function testPayloadCarriesRoutingIdsAndNoEncryptedField(): void
    {
        [$family, $author, $recipient] = $this->family();
        $author->getUser()->setFullName('Sophie Durand');
        $this->pref($recipient->getUser(), NotificationCategory::Custody, NotificationChannel::Push, true);
        $this->pref($recipient->getUser(), NotificationCategory::Exchanges, NotificationChannel::Push, true);
        $this->device($recipient->getUser(), 'jeton-contenu', DevicePlatform::Ios);

        $this->handler()(new SendPushNotification(
            'custody_changed:exc-1',
            (string) $recipient->getUser()->getId(),
            (string) $family->getId(),
            'custody_changed',
            ['date' => '2026-09-12', 'membershipId' => (string) $author->getId()],
        ));
        $this->handler()($this->message($recipient, $family));

        [$custody, $exchange] = array_map(static fn (array $s) => $s['content'], $this->fake()->sent);

        self::assertSame('Planning de garde modifié', $custody->title);
        self::assertStringNotContainsString('Sophie', $custody->title.$custody->body); // 🔒 full_name
        self::assertSame([
            'eventId' => 'custody_changed:exc-1',
            'type' => 'custody_changed',
            'category' => 'custody',
            'familyId' => (string) $family->getId(),
            'date' => '2026-09-12',
        ], $custody->data);

        self::assertSame('Nouveau message', $exchange->title);
        self::assertSame('Dans l\'échange « Vacances de Noël »', $exchange->body);
        self::assertSame('ex-42', $exchange->data['exchangeId']);
        self::assertArrayNotHasKey('title', $exchange->data);
    }

    // --- RGPD -------------------------------------------------------------------------------

    public function testAnonymizationDeletesDevices(): void
    {
        [, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->device($user, 'jeton-efface-1');
        $this->device($user, 'jeton-efface-2')->revoke(Clock::get()->now());
        $this->em()->flush();

        $anonymizer = self::getContainer()->get(Anonymizer::class);
        self::assertInstanceOf(Anonymizer::class, $anonymizer);
        $anonymizer->anonymize($user, $user, null);

        self::assertSame(0, self::getContainer()->get(DeviceTokenRepository::class)->count(['user' => $user]));
    }

    public function testPurgeRemovesRetiredAndDormantTokensOnly(): void
    {
        [, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $now = Clock::get()->now();
        $this->device($user, 'jeton-revoque-ancien')->revoke($now->modify('-40 days'));
        $this->device($user, 'jeton-invalide-ancien')->invalidate($now->modify('-40 days'));
        $this->device($user, 'jeton-revoque-recent')->revoke($now->modify('-2 days'));
        $this->device($user, 'jeton-dormant')->seen($now->modify('-300 days'));
        $this->device($user, 'jeton-vivant');
        $this->em()->flush();

        $purger = self::getContainer()->get(Purger::class);
        self::assertInstanceOf(Purger::class, $purger);
        self::assertSame(3, $this->purgeCandidates($purger));
        self::assertSame(5, self::getContainer()->get(DeviceTokenRepository::class)->count(['user' => $user]));

        $purger->run(RgpdMode::Live);
        $this->em()->clear();
        $left = array_map(
            static fn (DeviceToken $d): string => $d->getToken(),
            self::getContainer()->get(DeviceTokenRepository::class)->findBy(['user' => $user->getId()]),
        );
        sort($left);
        self::assertSame(['jeton-revoque-recent', 'jeton-vivant'], $left);
        self::assertSame(0, $this->purgeCandidates($purger)); // idempotente
    }

    private function purgeCandidates(Purger $purger): int
    {
        foreach ($purger->run(RgpdMode::DryRun)->lines as $line) {
            if ('device_tokens' === $line->scope) {
                return $line->candidates;
            }
        }
        self::fail('La règle device_tokens doit figurer au rapport de purge.');
    }

    public function testExportListsDevicesWithoutToken(): void
    {
        [, , $recipient] = $this->family();
        $user = $recipient->getUser();
        $this->device($user, 'jeton-exporte', DevicePlatform::Ios)->setDeviceModel('iPhone 15');
        $this->em()->flush();

        $exporter = self::getContainer()->get(UserDataExporter::class);
        self::assertInstanceOf(UserDataExporter::class, $exporter);
        $export = $exporter->build($user);

        self::assertCount(1, $export['devices']);
        self::assertSame('ios', $export['devices'][0]['platform']);
        self::assertSame('iPhone 15', $export['devices'][0]['deviceModel']);
        self::assertStringNotContainsString('jeton-exporte', json_encode($export, \JSON_THROW_ON_ERROR));
    }
}
