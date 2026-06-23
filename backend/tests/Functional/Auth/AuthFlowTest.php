<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Message\AuthEmailType;
use App\Message\SendAuthEmail;
use App\Security\Encryption\FieldEncryptor;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Parcours d'auth de bout en bout (US-AUTH-1/2) : inscription → vérification → connexion →
 * rafraîchissement rotatif, plus les garanties anti-énumération et le refus avant vérification.
 */
final class AuthFlowTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const string PASSWORD = 'CorrectHorse42!';

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function json(Client $client, string $method, string $uri, array $payload = []): array
    {
        $response = $client->request($method, $uri, [
            'json' => $payload,
            'headers' => ['Accept' => 'application/json'],
        ]);

        return ['status' => $response->getStatusCode(), 'body' => $this->safeArray($response)];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeArray(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable) {
            return [];
        }
    }

    private function lastEmailToken(AuthEmailType $type): string
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        $encryptor = self::getContainer()->get(FieldEncryptor::class);
        self::assertInstanceOf(FieldEncryptor::class, $encryptor);

        foreach (array_reverse($transport->getSent()) as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof SendAuthEmail && $message->type === $type) {
                // Le jeton est chiffré en transit ; on le déchiffre pour le test.
                $token = $encryptor->decrypt($message->token);
                self::assertNotNull($token);

                return $token;
            }
        }

        self::fail('Aucun e-mail '.$type->value.' dispatché.');
    }

    public function testRegisterVerifyLoginRefresh(): void
    {
        $client = self::createClient();
        // Conserve le même kernel entre les requêtes : l'état du transport en mémoire (e-mails
        // dispatchés) persiste, ce qui permet de capter le jeton de vérification.
        $client->disableReboot();
        $email = 'alice@example.com';

        // 1. Inscription : 202, aucun token délivré (décision D1).
        $register = $this->json($client, 'POST', '/api/auth/register', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertSame(202, $register['status']);
        self::assertArrayNotHasKey('accessToken', $register['body']);

        // Le jeton de vérification est capté immédiatement (le transport en mémoire est réinitialisé
        // entre deux requêtes du client de test).
        $token = $this->lastEmailToken(AuthEmailType::Verification);

        // 2. Connexion refusée tant que l'e-mail n'est pas vérifié (403).
        $beforeVerify = $this->json($client, 'POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertSame(403, $beforeVerify['status']);

        // 3. Vérification de l'e-mail via le jeton capté.
        $verify = $this->json($client, 'POST', '/api/auth/verify-email', ['token' => $token]);
        self::assertSame(200, $verify['status']);

        // 4. Connexion : JWT + refresh.
        $login = $this->json($client, 'POST', '/api/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
        self::assertSame(200, $login['status']);
        self::assertNotEmpty($login['body']['accessToken']);
        self::assertNotEmpty($login['body']['refreshToken']);
        $refreshToken = $login['body']['refreshToken'];

        // 5. Rafraîchissement rotatif : nouveau refresh différent de l'ancien.
        $refresh = $this->json($client, 'POST', '/api/auth/refresh', ['refreshToken' => $refreshToken]);
        self::assertSame(200, $refresh['status']);
        self::assertNotEmpty($refresh['body']['accessToken']);
        self::assertNotSame($refreshToken, $refresh['body']['refreshToken']);

        // 6. Réutilisation de l'ancien refresh (déjà tourné) → 401 (détection de rejeu).
        $reuse = $this->json($client, 'POST', '/api/auth/refresh', ['refreshToken' => $refreshToken]);
        self::assertSame(401, $reuse['status']);
    }

    public function testRegisterIsAntiEnumerating(): void
    {
        $client = self::createClient();
        $payload = ['email' => 'bob@example.com', 'password' => self::PASSWORD];

        $first = $this->json($client, 'POST', '/api/auth/register', $payload);
        $second = $this->json($client, 'POST', '/api/auth/register', $payload);

        // Réponse identique que l'e-mail existe déjà ou non.
        self::assertSame(202, $first['status']);
        self::assertSame(202, $second['status']);
        self::assertSame($first['body'], $second['body']);
    }

    public function testLoginWithWrongPasswordIsGenericUnauthorized(): void
    {
        $client = self::createClient();
        $unknown = $this->json($client, 'POST', '/api/auth/login', ['email' => 'ghost@example.com', 'password' => self::PASSWORD]);

        self::assertSame(401, $unknown['status']);
    }

    public function testValidationRejectsWeakPassword(): void
    {
        $client = self::createClient();
        $weak = $this->json($client, 'POST', '/api/auth/register', ['email' => 'carol@example.com', 'password' => 'short']);

        self::assertSame(422, $weak['status']);
    }
}
