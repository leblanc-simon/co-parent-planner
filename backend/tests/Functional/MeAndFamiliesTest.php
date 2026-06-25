<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * US-SET-1 (/api/me) et US-ONB-1 (/api/families) : profil, onboarding, scope par utilisateur,
 * refus sans authentification.
 */
final class MeAndFamiliesTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const string PASSWORD = 'CorrectHorse42!';

    private function createActiveUser(string $email): void
    {
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail($email)
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));

        $em->persist($user);
        $em->flush();
    }

    private function login(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/auth/login', [
            'json' => ['email' => $email, 'password' => self::PASSWORD],
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $token = $response->toArray()['accessToken'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    /**
     * @return array{0: Client, 1: string} client + bearer token d'un utilisateur actif
     */
    private function authenticatedClient(string $email): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->createActiveUser($email);

        return [$client, $this->login($client, $email)];
    }

    public function testMeRequiresAuthentication(): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/api/me', ['headers' => ['Accept' => 'application/json']]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetAndPatchMe(): void
    {
        [$client, $token] = $this->authenticatedClient('me@example.com');
        $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $get = $client->request('GET', '/api/me', ['headers' => $auth]);
        self::assertSame(200, $get->getStatusCode());
        $body = $get->toArray();
        self::assertSame('me@example.com', $body['email']);
        self::assertSame('fr-FR', $body['locale']);
        self::assertNull($body['fullName']);
        self::assertNull($body['avatarUrl']);

        $patch = $client->request('PATCH', '/api/me', [
            'json' => ['fullName' => 'Alice Martin', 'phone' => '+33612345678'],
            'headers' => $auth + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $patch->getStatusCode());
        self::assertSame('Alice Martin', $patch->toArray()['fullName']);

        // Persistance des champs chiffrés (relecture).
        $reread = $client->request('GET', '/api/me', ['headers' => $auth]);
        self::assertSame('Alice Martin', $reread->toArray()['fullName']);
        self::assertSame('+33612345678', $reread->toArray()['phone']);
    }

    public function testCreateAndListFamilies(): void
    {
        [$client, $token] = $this->authenticatedClient('parent@example.com');
        $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $empty = $client->request('GET', '/api/families', ['headers' => $auth]);
        self::assertSame(200, $empty->getStatusCode());
        self::assertCount(0, $empty->toArray());

        $create = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Martin', 'type' => 'separated'],
            'headers' => $auth,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $family = $create->toArray();
        self::assertSame('Famille Martin', $family['name']);
        self::assertSame('separated', $family['type']);
        self::assertSame('parent', $family['role']);
        self::assertTrue($family['isAdmin']);
        self::assertTrue($family['hasCustody']);

        $list = $client->request('GET', '/api/families', ['headers' => $auth]);
        self::assertCount(1, $list->toArray());
    }

    public function testCreateFamilyRejectsInvalidType(): void
    {
        [$client, $token] = $this->authenticatedClient('bad@example.com');

        $response = $client->request('POST', '/api/families', [
            'json' => ['name' => 'X', 'type' => 'polynuclear'],
            'headers' => ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testGetFamilyItemReturnsFamilyForMember(): void
    {
        [$client, $token] = $this->authenticatedClient('member@example.com');
        $auth = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

        $create = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Durand', 'type' => 'separated'],
            'headers' => $auth,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $id = $create->toArray()['id'];
        self::assertIsString($id);

        // Le membre actif obtient le détail de sa famille.
        $get = $client->request('GET', '/api/families/'.$id, ['headers' => $auth]);
        self::assertSame(200, $get->getStatusCode());
        self::assertSame('Famille Durand', $get->toArray()['name']);

        // Famille inconnue : 404 (anti-énumération, jamais membre).
        $missing = $client->request('GET', '/api/families/'.Uuid::v4()->toRfc4122(), ['headers' => $auth]);
        self::assertSame(404, $missing->getStatusCode());
    }
}
