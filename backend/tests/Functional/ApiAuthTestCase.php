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

/**
 * Base des tests fonctionnels authentifiés : crée un utilisateur actif et fournit un client
 * porteur d'un JWT valide.
 */
abstract class ApiAuthTestCase extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected const string PASSWORD = 'CorrectHorse42!';

    protected function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    protected function createActiveUser(string $email): User
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail($email)
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function login(Client $client, string $email): string
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
     * @return array{0: Client, 1: string} client + en-tête bearer prêt à l'emploi
     */
    protected function authenticatedClient(string $email): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->createActiveUser($email);

        return [$client, $this->login($client, $email)];
    }

    /**
     * @return array<string, string>
     */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }
}
