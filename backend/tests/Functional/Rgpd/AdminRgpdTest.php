<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rgpd;

use ApiPlatform\Metadata\Post;
use App\Dto\Admin\StepUpRequest;
use App\Entity\User;
use App\Security\Auth\Exception\TooManyAttemptsException;
use App\State\Admin\StepUpProcessor;
use App\Tests\Functional\ApiAuthTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * US-RGPD-1..4 — administration technique : rôle ROLE_ADMIN distinct des rôles familiaux + auth
 * renforcée (step-up). Registre, journal des opérations, rapport de conformité, purge dry-run/live.
 */
final class AdminRgpdTest extends ApiAuthTestCase
{
    public function testAdminEndpointsRequireRoleThenStepUp(): void
    {
        [$client, $token] = $this->authenticatedClient('tech@example.com');
        $auth = $this->bearer($token);

        // 1. Utilisateur non-admin → 403 (ROLE_ADMIN exigé par access_control).
        self::assertSame(403, $client->request('GET', '/api/admin/rgpd/register', ['headers' => $auth])->getStatusCode());

        $this->makeTechnicalAdmin('tech@example.com');

        // 2. Admin technique SANS step-up → 403 (auth renforcée requise).
        self::assertSame(403, $client->request('GET', '/api/admin/rgpd/register', ['headers' => $auth])->getStatusCode());

        // 3. Step-up par ré-authentification.
        $stepUp = $client->request('POST', '/api/admin/step-up', ['headers' => $auth, 'json' => ['password' => self::PASSWORD]]);
        self::assertSame(200, $stepUp->getStatusCode());
        $grant = $stepUp->toArray()['token'];
        self::assertNotEmpty($grant);
        $adminAuth = $auth + ['X-Admin-Step-Up' => $grant];

        // 4. Registre des traitements.
        $register = $client->request('GET', '/api/admin/rgpd/register', ['headers' => $adminAuth]);
        self::assertSame(200, $register->getStatusCode());
        self::assertNotEmpty($register->toArray());

        // 5. Journal des opérations (le step-up lui-même y a créé des lignes d'audit, pas d'opération).
        self::assertSame(200, $client->request('GET', '/api/admin/rgpd/operations', ['headers' => $adminAuth])->getStatusCode());

        // 6. Rapport de conformité (hébergement UE confirmé, règles de rétention présentes).
        $report = $client->request('GET', '/api/admin/rgpd/compliance-report', ['headers' => $adminAuth]);
        self::assertSame(200, $report->getStatusCode());
        $reportBody = $report->toArray();
        self::assertTrue($reportBody['euHosting']);
        self::assertNotEmpty($reportBody['retentionRules']);

        // 7. Purge dry-run (défaut) : ne modifie rien.
        $dry = $client->request('POST', '/api/admin/rgpd/purge', ['headers' => $adminAuth, 'json' => []]);
        self::assertSame(200, $dry->getStatusCode());
        self::assertSame('dry_run', $dry->toArray()['mode']);

        // 8. Purge live : idempotente, journalisée.
        $live = $client->request('POST', '/api/admin/rgpd/purge?dryRun=false', ['headers' => $adminAuth, 'json' => []]);
        self::assertSame(200, $live->getStatusCode());
        self::assertSame('live', $live->toArray()['mode']);
    }

    public function testStepUpRejectsWrongPassword(): void
    {
        [$client, $token] = $this->authenticatedClient('tech2@example.com');
        $this->makeTechnicalAdmin('tech2@example.com');

        $response = $client->request('POST', '/api/admin/step-up', [
            'headers' => $this->bearer($token),
            'json' => ['password' => 'not-the-password'],
        ]);
        self::assertSame(401, $response->getStatusCode());
    }

    public function testFamilyRoleCannotAccessAdmin(): void
    {
        [$client, $token] = $this->authenticatedClient('parent@example.com');
        // Un utilisateur ordinaire (rôle familial uniquement) n'accède jamais à /api/admin/rgpd/*.
        self::assertSame(403, $client->request('GET', '/api/admin/rgpd/operations', ['headers' => $this->bearer($token)])->getStatusCode());
        self::assertSame(403, $client->request('POST', '/api/admin/step-up', ['headers' => $this->bearer($token), 'json' => ['password' => self::PASSWORD]])->getStatusCode());
    }

    public function testStepUpIsRateLimited(): void
    {
        // Anti brute-force : on épuise le limiter step-up (clé identique à celle du processor hors
        // requête HTTP = « anon|<id> »), puis on exerce le processor directement → 429.
        $user = $this->createActiveUser('techrl@example.com');
        $user->setTechnicalAdmin(true);
        $this->em()->flush();
        $container = self::getContainer();

        $factory = $container->get('limiter.admin_step_up');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $factory);
        $limiter = $factory->create('anon|'.$user->getId());
        for ($i = 0; $i < 5; ++$i) {
            $limiter->consume(1);
        }

        $tokenStorage = $container->get(TokenStorageInterface::class);
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

        $processor = $container->get(StepUpProcessor::class);
        self::assertInstanceOf(StepUpProcessor::class, $processor);
        $request = new StepUpRequest();
        $request->password = self::PASSWORD;

        $this->expectException(TooManyAttemptsException::class);
        $processor->process($request, new Post());
    }

    private function makeTechnicalAdmin(string $email): void
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $user->setTechnicalAdmin(true);
        $this->em()->flush();
    }
}
