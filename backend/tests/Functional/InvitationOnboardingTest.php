<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Message\SendAuthEmail;
use App\Security\Auth\TokenHasher;
use App\Security\Encryption\BlindIndexHasher;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Flux d'invitation simplifié (§3.3) : création de compte / connexion + acceptation + session en un
 * appel, sans e-mail de vérification ni reconnexion. Couvre les deux endpoints publics et les
 * garde-fous de sécurité (usage unique, expiry, mot de passe exigé sur compte existant).
 */
final class InvitationOnboardingTest extends ApiAuthTestCase
{
    private function client(): Client
    {
        $client = self::createClient();
        // Conserve le kernel entre requêtes : l'état du transport en mémoire (e-mails) persiste.
        $client->disableReboot();

        return $client;
    }

    /** Amorce une invitation pending (avec famille + admin fondateur) et renvoie son token en clair. */
    private function seedInvitation(string $email, string $plainToken, string $familyName = 'Famille Onboarding', MembershipRole $role = MembershipRole::Parent, int $expiresInDays = 7): void
    {
        $hasher = self::getContainer()->get(TokenHasher::class);
        $blind = self::getContainer()->get(BlindIndexHasher::class);
        self::assertInstanceOf(TokenHasher::class, $hasher);
        self::assertInstanceOf(BlindIndexHasher::class, $blind);

        $founder = (new User())->setEmail('founder-'.$email)->setStatus(UserStatus::Active);
        $founder->setPasswordHash('x');
        $family = (new Family())->setName($familyName)->setType(FamilyType::Separated);
        $founderMembership = (new Membership())->setUser($founder)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);

        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail($email)
            ->setEmailHash($blind->hash($email))
            ->setRole($role)
            ->setProposedRights([])
            ->setProposedAdmin(MembershipRole::Parent === $role)
            ->setTokenHash($hasher->hash($plainToken))
            ->setExpiresAt(Clock::get()->now()->modify(($expiresInDays >= 0 ? '+' : '-').abs($expiresInDays).' days'))
            ->setStatus(InvitationStatus::Pending);

        $em = $this->em();
        foreach ([$founder, $family, $founderMembership, $invitation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
    }

    private function seedUser(string $email, UserStatus $status): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail($email)->setStatus($status);
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        if (UserStatus::Active === $status) {
            $user->setEmailVerifiedAt(Clock::get()->now());
        }
        $this->em()->persist($user);
        $this->em()->flush();
    }

    private function assertNoVerificationEmailSent(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        foreach ($transport->getSent() as $envelope) {
            self::assertNotInstanceOf(SendAuthEmail::class, $envelope->getMessage(), 'Aucun e-mail de vérification ne doit être émis sur le parcours d\'invitation.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function post(Client $client, string $uri, array $payload): array
    {
        $response = $client->request('POST', $uri, ['json' => $payload, 'headers' => ['Accept' => 'application/json']]);

        return ['status' => $response->getStatusCode(), 'body' => $response->toArray(false)];
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(string $email): array
    {
        $row = $this->em()->getConnection()->fetchAssociative('SELECT status, email_verified_at FROM users WHERE email = ?', [$email]);
        self::assertIsArray($row);

        return $row;
    }

    private function membershipCount(string $email): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM memberships m JOIN users u ON u.id = m.user_id WHERE u.email = ?',
            [$email],
        );
    }

    // --- register-and-accept (compte neuf) ---

    public function testRegisterAndAcceptCreatesActiveUserAndOpensSession(): void
    {
        $client = $this->client();
        $this->seedInvitation('newbie@example.com', 'tok-new', familyName: 'Les Nouveaux');

        $res = $this->post($client, '/api/auth/register-and-accept-invitation', [
            'token' => 'tok-new', 'password' => self::PASSWORD, 'fullName' => 'Nouvel Invité',
        ]);

        self::assertSame(200, $res['status']);
        self::assertIsString($res['body']['accessToken'] ?? null);
        self::assertIsString($res['body']['refreshToken'] ?? null);

        // Compte actif, e-mail vérifié par preuve du token — et membership créé.
        $row = $this->userRow('newbie@example.com');
        self::assertSame('active', $row['status']);
        self::assertNotNull($row['email_verified_at']);
        self::assertSame(1, $this->membershipCount('newbie@example.com'));

        // La friction retirée : aucun e-mail de vérification.
        $this->assertNoVerificationEmailSent();

        // Session réelle : le jeton renvoyé donne accès à la famille rejointe.
        $families = $client->request('GET', '/api/families', [
            'headers' => $this->bearer($res['body']['accessToken']),
        ])->toArray();
        self::assertCount(1, $families);
        self::assertSame('Les Nouveaux', $families[0]['name']);
    }

    public function testRegisterAndAcceptRejectsWhenAccountAlreadyExists(): void
    {
        $client = $this->client();
        $this->seedUser('dup@example.com', UserStatus::Active);
        $this->seedInvitation('dup@example.com', 'tok-dup');

        $res = $this->post($client, '/api/auth/register-and-accept-invitation', ['token' => 'tok-dup', 'password' => self::PASSWORD]);
        self::assertSame(409, $res['status']);
        self::assertSame('/errors/account-already-exists', $res['body']['type']);
    }

    public function testRegisterAndAcceptExpiredReturns410(): void
    {
        $client = $this->client();
        $this->seedInvitation('late@example.com', 'tok-late', expiresInDays: -1);

        $res = $this->post($client, '/api/auth/register-and-accept-invitation', ['token' => 'tok-late', 'password' => self::PASSWORD]);
        self::assertSame(410, $res['status']);
    }

    public function testRegisterAndAcceptInvalidTokenReturns400(): void
    {
        $client = $this->client();
        $res = $this->post($client, '/api/auth/register-and-accept-invitation', ['token' => 'inconnu', 'password' => self::PASSWORD]);
        self::assertSame(400, $res['status']);
    }

    public function testRegisterAndAcceptIsSingleUse(): void
    {
        $client = $this->client();
        $this->seedInvitation('once@example.com', 'tok-once');

        $first = $this->post($client, '/api/auth/register-and-accept-invitation', ['token' => 'tok-once', 'password' => self::PASSWORD]);
        self::assertSame(200, $first['status']);

        // Rejeu : l'invitation n'est plus pending → 400 générique non énumérant.
        $second = $this->post($client, '/api/auth/register-and-accept-invitation', ['token' => 'tok-once', 'password' => self::PASSWORD]);
        self::assertSame(400, $second['status']);
    }

    // --- login-and-accept (compte existant) ---

    public function testLoginAndAcceptJoinsWithCorrectPassword(): void
    {
        $client = $this->client();
        $this->seedUser('member@example.com', UserStatus::Active);
        $this->seedInvitation('member@example.com', 'tok-log', familyName: 'Famille Log');

        $res = $this->post($client, '/api/auth/login-and-accept-invitation', ['token' => 'tok-log', 'password' => self::PASSWORD]);
        self::assertSame(200, $res['status']);
        self::assertIsString($res['body']['accessToken'] ?? null);
        self::assertSame(1, $this->membershipCount('member@example.com'));
    }

    public function testLoginAndAcceptWrongPasswordReturns401(): void
    {
        $client = $this->client();
        $this->seedUser('wrongpass@example.com', UserStatus::Active);
        $this->seedInvitation('wrongpass@example.com', 'tok-wp');

        $res = $this->post($client, '/api/auth/login-and-accept-invitation', ['token' => 'tok-wp', 'password' => 'MauvaisMotDePasse1!']);
        self::assertSame(401, $res['status']);
        self::assertSame(0, $this->membershipCount('wrongpass@example.com'), 'Aucune adhésion sans le bon mot de passe.');
    }

    public function testLoginAndAcceptActivatesPendingAccount(): void
    {
        $client = $this->client();
        $this->seedUser('pending@example.com', UserStatus::Pending);
        $this->seedInvitation('pending@example.com', 'tok-pending', familyName: 'Famille Pending');

        // Le mot de passe existant est exigé (jamais écrasé) ; le token prouve la boîte → activation.
        $res = $this->post($client, '/api/auth/login-and-accept-invitation', ['token' => 'tok-pending', 'password' => self::PASSWORD]);
        self::assertSame(200, $res['status']);

        $row = $this->userRow('pending@example.com');
        self::assertSame('active', $row['status']);
        self::assertNotNull($row['email_verified_at']);
        self::assertSame(1, $this->membershipCount('pending@example.com'));
    }

    public function testLoginAndAcceptNoAccountReturns409(): void
    {
        $client = $this->client();
        $this->seedInvitation('ghost@example.com', 'tok-ghost');

        $res = $this->post($client, '/api/auth/login-and-accept-invitation', ['token' => 'tok-ghost', 'password' => self::PASSWORD]);
        self::assertSame(409, $res['status']);
        self::assertSame('/errors/account-requires-registration', $res['body']['type']);
    }
}
