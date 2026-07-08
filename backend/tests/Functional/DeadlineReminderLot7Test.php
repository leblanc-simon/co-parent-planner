<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Exchange;
use App\Entity\ExchangeOption;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\ExchangeStatus;
use App\Enum\ExchangeType;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Message\SendDeadlineReminders;
use App\Message\SendNotification;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Lot 7 §3.9 — rappels d'échéance : décision dépassée → « en retard », échéance à J+1 → rappel ;
 * décision tranchée → aucun rappel. Horloge gelée (aucune date en dur).
 */
final class DeadlineReminderLot7Test extends ApiAuthTestCase
{
    use ClockSensitiveTrait;

    private function decision(Family $family, ExchangeStatus $status, ?string $deadline): Exchange
    {
        $exchange = (new Exchange())
            ->setFamily($family)
            ->setType(ExchangeType::Decision)
            ->setTitle('Décision '.substr(uniqid(), -5))
            ->setStatus($status);
        if (null !== $deadline) {
            $exchange->setDeadline(new \DateTimeImmutable($deadline));
        }
        $this->em()->persist($exchange);
        foreach (['A', 'B'] as $i => $label) {
            $this->em()->persist((new ExchangeOption())->setExchange($exchange)->setLabel($label)->setPosition($i));
        }

        return $exchange;
    }

    private User $parent;

    private function familyWithParent(): Family
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $family = (new Family())->setName('Famille Rappels')->setType(FamilyType::Separated);
        $user = (new User())->setEmail('rem-'.uniqid().'@example.com')->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $this->em()->persist($family);
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->parent = $user;

        return $family;
    }

    /**
     * Rappels dispatchés **pour le parent de la famille de test** (isole des décisions d'autres tests
     * dans la base partagée).
     *
     * @return list<SendNotification>
     */
    private function runReminders(): array
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);
        self::assertInstanceOf(MessageBusInterface::class, $bus);
        $bus->dispatch(new SendDeadlineReminders());

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $mine = (string) $this->parent->getId();
        $out = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendNotification && $message->recipientUserId === $mine) {
                $out[] = $message;
            }
        }

        return $out;
    }

    public function testOverdueAndUpcomingAndDecided(): void
    {
        self::createClient();
        static::mockTime(new \DateTimeImmutable('2026-06-25 10:00', new \DateTimeZone('Europe/Paris')));

        $family = $this->familyWithParent();
        $this->decision($family, ExchangeStatus::Open, '2026-06-24');   // hier → en retard
        $this->decision($family, ExchangeStatus::Open, '2026-06-25');   // aujourd'hui → rappel J-1
        $this->decision($family, ExchangeStatus::Decided, '2026-06-24'); // tranchée → aucun rappel
        $this->em()->flush();

        $types = array_map(static fn (SendNotification $n): string => $n->type, $this->runReminders());

        self::assertContains('decision_overdue', $types);
        self::assertContains('decision_reminder', $types);
    }

    public function testFarFutureDeadlineNoReminder(): void
    {
        self::createClient();
        static::mockTime(new \DateTimeImmutable('2026-06-25 10:00', new \DateTimeZone('Europe/Paris')));

        $family = $this->familyWithParent();
        $this->decision($family, ExchangeStatus::Open, '2026-12-31'); // lointain → aucun rappel
        $this->em()->flush();

        $reminders = array_filter(
            $this->runReminders(),
            static fn (SendNotification $n): bool => \in_array($n->type, ['decision_overdue', 'decision_reminder'], true),
        );
        self::assertCount(0, $reminders);
    }
}
