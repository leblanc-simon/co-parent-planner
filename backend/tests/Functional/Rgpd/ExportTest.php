<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rgpd;

use ApiPlatform\Metadata\Post;
use App\Entity\User;
use App\Message\ExportUserData;
use App\MessageHandler\ExportUserDataHandler;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\State\Rgpd\ExportProcessor;
use App\Tests\Functional\ApiAuthTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * US-SET-4 — export RGPD asynchrone : opération créée (202), assemblage par le worker, lien signé
 * temporaire, refus des tiers, rate-limiting.
 */
final class ExportTest extends ApiAuthTestCase
{
    public function testExportFlowProducesSignedDownload(): void
    {
        [$client, $token] = $this->authenticatedClient('exporter@example.com');
        $auth = $this->bearer($token);

        $post = $client->request('POST', '/api/me/export', ['headers' => $auth, 'json' => []]);
        self::assertSame(202, $post->getStatusCode());
        $created = $post->toArray();
        $opId = $created['id'];
        self::assertSame('pending', $created['status']);

        // Le transport async est in-memory en test : on exécute le worker manuellement.
        $this->runExportWorker($opId, 'exporter@example.com');

        $status = $client->request('GET', '/api/me/export/'.$opId, ['headers' => $auth]);
        self::assertSame(200, $status->getStatusCode());
        $body = $status->toArray();
        self::assertSame('done', $body['status']);
        self::assertNotNull($body['downloadUrl']);

        $download = $client->request('GET', $body['downloadUrl'], ['headers' => $auth]);
        self::assertSame(200, $download->getStatusCode());
        self::assertStringContainsString('application/zip', $download->getHeaders()['content-type'][0]);
        self::assertStringStartsWith('PK', $download->getContent()); // signature ZIP
    }

    public function testTierCannotUseAnotherUsersSignedLink(): void
    {
        [$client, $token] = $this->authenticatedClient('owner@example.com');
        $post = $client->request('POST', '/api/me/export', ['headers' => $this->bearer($token), 'json' => []]);
        $opId = $post->toArray()['id'];
        $this->runExportWorker($opId, 'owner@example.com');

        $url = $client->request('GET', '/api/me/export/'.$opId, ['headers' => $this->bearer($token)])->toArray()['downloadUrl'];

        // Un autre utilisateur authentifié tente d'utiliser le lien signé : refus (scope au sujet).
        $this->createActiveUser('intruder@example.com');
        $intruderToken = $this->login($client, 'intruder@example.com');
        $response = $client->request('GET', $url, ['headers' => $this->bearer($intruderToken)]);
        self::assertSame(403, $response->getStatusCode());
    }

    public function testExportIsRateLimited(): void
    {
        // Le cache array de test est réinitialisé à chaque requête HTTP : on exerce donc le processor
        // directement (sans HTTP), avec le même limiter (array) préalablement épuisé pour le sujet.
        $user = $this->createActiveUser('spammer@example.com');
        $container = self::getContainer();

        $factory = $container->get('limiter.rgpd_export');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $factory);
        $limiter = $factory->create((string) $user->getId());
        for ($i = 0; $i < 5; ++$i) {
            $limiter->consume(1);
        }

        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

        $processor = $container->get(ExportProcessor::class);
        self::assertInstanceOf(ExportProcessor::class, $processor);

        $this->expectException(TooManyAttemptsException::class);
        $processor->process(null, new Post());
    }

    private function runExportWorker(string $operationId, string $email): void
    {
        $handler = self::getContainer()->get(ExportUserDataHandler::class);
        self::assertInstanceOf(ExportUserDataHandler::class, $handler);
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        $handler(new ExportUserData($operationId, (string) $user->getId()));
    }
}
