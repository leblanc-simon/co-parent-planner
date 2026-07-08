<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\NotificationPref;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use App\Message\SendNotification;
use App\Repository\FamilyRepository;
use App\Repository\UserRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 7 — pipeline de notification : une mutation métier (ouverture d'échange) dispatch un
 * `SendNotification` vers les destinataires habilités **hors auteur**, filtré par préférence e-mail.
 */
final class NotificationPipelineLot7Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} client, headers scopés A, familyId */
    private function familyWithSecondParent(string $emailA, string $emailB): array
    {
        [$client, $token] = $this->authenticatedClient($emailA);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Notif', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];

        $family = $this->findFamily($familyId);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $userB = (new User())->setEmail($emailB)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $userB->setPasswordHash($hasher->hashPassword($userB, self::PASSWORD));
        $membershipB = (new Membership())->setUser($userB)->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $membershipB->addRight(new Right(RightDomain::Exchanges, RightLevel::Edit));
        $this->em()->persist($userB);
        $this->em()->persist($membershipB);
        $this->em()->flush();

        return [$client, $auth + ['X-Family-Id' => $familyId], $familyId];
    }

    private function findFamily(string $familyId): Family
    {
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        return $family;
    }

    private function userId(string $email): string
    {
        $users = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        $user = $users->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return (string) $user->getId();
    }

    /** @return list<SendNotification> */
    private function sentNotifications(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $out = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendNotification) {
                $out[] = $message;
            }
        }

        return $out;
    }

    public function testOpeningExchangeNotifiesOtherParentNotAuthor(): void
    {
        [$client, $h] = $this->familyWithSecondParent('notif-a@example.com', 'notif-b@example.com');

        $client->request('POST', '/api/families/'.$h['X-Family-Id'].'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Sujet notif'],
            'headers' => $h,
        ]);

        $notifs = array_values(array_filter($this->sentNotifications(), static fn (SendNotification $n): bool => 'exchange_opened' === $n->type));
        self::assertCount(1, $notifs); // uniquement B (A = auteur exclu)
        self::assertSame($this->userId('notif-b@example.com'), $notifs[0]->recipientUserId);
        self::assertNotSame($this->userId('notif-a@example.com'), $notifs[0]->recipientUserId);
    }

    public function testDisabledPreferenceSuppressesNotification(): void
    {
        [$client, $h] = $this->familyWithSecondParent('notif-c@example.com', 'notif-d@example.com');

        // B désactive la catégorie exchanges (pref e-mail).
        $userB = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'notif-d@example.com']);
        self::assertInstanceOf(User::class, $userB);
        $pref = (new NotificationPref())->setUser($userB)->setCategory(NotificationCategory::Exchanges)->setChannel(NotificationChannel::Email)->setEnabled(false);
        $this->em()->persist($pref);
        $this->em()->flush();

        $client->request('POST', '/api/families/'.$h['X-Family-Id'].'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Silencieux'],
            'headers' => $h,
        ]);

        $idD = $this->userId('notif-d@example.com');
        $forD = array_filter(
            $this->sentNotifications(),
            static fn (SendNotification $n): bool => 'exchange_opened' === $n->type && $n->recipientUserId === $idD,
        );
        self::assertCount(0, $forD); // pref désactivée → aucune notif pour D
    }
}
