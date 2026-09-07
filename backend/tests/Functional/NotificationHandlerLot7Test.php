<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\NotificationStatus;
use App\Enum\UserStatus;
use App\Message\SendNotification;
use App\MessageHandler\SendNotificationHandler;
use App\Repository\NotificationDispatchRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\Clock;
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

    /**
     * Nommage du foyer dans l'e-mail (évolution E3) : « Le 12 septembre 2026 est désormais chez
     * Sophie et Bob » plutôt que « le planning a été modifié », qui oblige à ouvrir l'application
     * pour savoir ce qui a changé.
     *
     * Le payload ne transporte que des **identifiants** : le nom, chiffré au repos (`full_name` 🔒),
     * est résolu à l'envoi. Un payload Messenger vit en clair dans `messenger_messages`, et y
     * recopier une identité la ferait fuiter dans un dump SQL brut (cdc §7).
     */
    public function testCustodyEmailNamesTheHousehold(): void
    {
        self::createClient();
        $recipient = $this->createActiveUser('household-recipient@example.com');
        [$family, $parent] = $this->seedCouple();

        $handler = self::getContainer()->get(SendNotificationHandler::class);
        self::assertInstanceOf(SendNotificationHandler::class, $handler);

        $handler(new SendNotification(
            'custody_changed:fact-household:'.$recipient->getId(),
            (string) $recipient->getId(),
            (string) $family->getId(),
            'custody_changed',
            ['date' => '2026-09-12', 'membershipId' => (string) $parent->getId()],
        ));

        $body = $this->lastEmailBody();
        self::assertStringContainsString('Sophie Durand et Bob Martin', $body);
        self::assertStringContainsString('12 septembre 2026', $body);
    }

    /**
     * Sans conjoint, le foyer est le parent seul — la phrase reste juste, elle ne devient pas
     * « Sophie et  ».
     */
    public function testCustodyEmailNamesASingleParent(): void
    {
        self::createClient();
        $recipient = $this->createActiveUser('household-single@example.com');
        [$family, $parent] = $this->seedCouple(withPartner: false);

        $handler = self::getContainer()->get(SendNotificationHandler::class);
        self::assertInstanceOf(SendNotificationHandler::class, $handler);

        $handler(new SendNotification(
            'custody_changed:fact-single:'.$recipient->getId(),
            (string) $recipient->getId(),
            (string) $family->getId(),
            'custody_changed',
            ['date' => '2026-09-12', 'membershipId' => (string) $parent->getId()],
        ));

        $body = $this->lastEmailBody();
        self::assertStringContainsString('chez Sophie Durand.', $body);
    }

    /**
     * Sans refs — changement de rythme, période de plusieurs jours, membre retiré depuis —, le
     * corps générique est conservé : un e-mail moins précis vaut mieux qu'une phrase à trous.
     */
    public function testCustodyEmailFallsBackToTheGenericBody(): void
    {
        self::createClient();
        $recipient = $this->createActiveUser('household-generic@example.com');
        [$family] = $this->seedCouple(withPartner: false);

        $handler = self::getContainer()->get(SendNotificationHandler::class);
        self::assertInstanceOf(SendNotificationHandler::class, $handler);

        $handler(new SendNotification(
            'custody_changed:fact-generic:'.$recipient->getId(),
            (string) $recipient->getId(),
            (string) $family->getId(),
            'custody_changed',
            [],
        ));

        $body = $this->lastEmailBody();
        self::assertStringContainsString('Le planning de garde de votre famille a été modifié.', $body);
        self::assertStringNotContainsString('désormais chez', $body);
    }

    /** @return array{0: Family, 1: Membership} */
    private function seedCouple(bool $withPartner = true): array
    {
        $em = $this->em();
        $family = (new Family())->setName('Famille Foyer')->setType(FamilyType::Separated);
        $em->persist($family);

        $parentUser = (new User())->setEmail('household-parent@example.com')->setFullName('Sophie Durand')
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $parentUser->setPasswordHash('x');
        $parent = (new Membership())->setUser($parentUser)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $em->persist($parentUser);
        $em->persist($parent);

        if ($withPartner) {
            $stepUser = (new User())->setEmail('household-step@example.com')->setFullName('Bob Martin')
                ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
            $stepUser->setPasswordHash('x');
            // La relation est portée par le beau-parent : la question « qui accompagne ce
            // parent ? » passe donc par la requête inverse, que `Household` encapsule.
            $step = (new Membership())->setUser($stepUser)->setFamily($family)
                ->setRole(MembershipRole::StepParent)->setStatus(MembershipStatus::Active)
                ->setPartnerMembership($parent);
            $em->persist($stepUser);
            $em->persist($step);
        }
        $em->flush();

        return [$family, $parent];
    }

    private function lastEmailBody(): string
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = array_values(array_filter(
            $transport->getSent(),
            static fn ($e): bool => $e->getMessage() instanceof SendEmailMessage,
        ));
        self::assertNotEmpty($sent);

        $last = end($sent);
        self::assertNotFalse($last);
        $message = $last->getMessage();
        self::assertInstanceOf(SendEmailMessage::class, $message);
        $email = $message->getMessage();
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $body = $email->getContext()['body'] ?? '';
        self::assertIsString($body);

        return $body;
    }
}
