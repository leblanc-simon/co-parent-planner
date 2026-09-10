<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Session web glissante ({@see \App\EventSubscriber\SlidingSessionSubscriber}).
 *
 * Exigence produit : au moins **une heure d'inactivité** tolérée. Le TTL du JWT n'y suffit pas
 * seul — c'est une durée de vie absolue, comptée depuis la connexion — et la navigation web n'a
 * aucun rattrapage de 401 (le cookie `refresh_token` est limité au chemin `/api/auth/refresh`).
 * D'où la ré-émission du JWT d'accès à chaque navigation, vérifiée ici.
 */
final class SlidingSessionTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';
    private const array SAME_ORIGIN = ['HTTP_SEC_FETCH_SITE' => 'same-origin'];

    /** La tolérance d'inactivité exigée : le TTL configuré ne doit jamais repasser en dessous. */
    private const int MIN_IDLE_SECONDS = 3600;

    public function testWebNavigationRenewsTheAccessCookie(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);
        $issuedAtLogin = $this->cookieValue($client, 'access_token');

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $renewed = $this->responseCookies($client)['access_token'] ?? null;
        self::assertInstanceOf(Cookie::class, $renewed, 'Une navigation authentifiée doit ré-émettre '
            .'le JWT d\'accès : sinon le délai court depuis la connexion, pas depuis la dernière activité.');
        self::assertTrue($renewed->isHttpOnly());
        self::assertSame('/', $renewed->getPath());
        self::assertSame(Cookie::SAMESITE_LAX, $renewed->getSameSite());
        $value = (string) $renewed->getValue();
        self::assertNotSame('', $value);

        // Le jeton ré-émis vaut bien la tolérance d'inactivité demandée, et le cookie vit aussi longtemps.
        $payload = $this->decode($value);
        self::assertGreaterThanOrEqual(self::MIN_IDLE_SECONDS, $payload['exp'] - $payload['iat']);
        self::assertGreaterThanOrEqual(
            Clock::get()->now()->getTimestamp() + self::MIN_IDLE_SECONDS,
            $renewed->getExpiresTime(),
        );

        // Le jeton de connexion expirait plus tôt : la fenêtre a bien glissé (ou, à la seconde près, tenu).
        self::assertGreaterThanOrEqual($this->decode($issuedAtLogin)['exp'], $payload['exp']);

        // Et il authentifie : la navigation suivante passe avec le seul cookie renouvelé.
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    /** Le refresh token n'est **pas** tourné au passage (voir le motif dans l'assertion). */
    public function testNavigationDoesNotRotateTheRefreshToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);
        $refresh = $this->cookieValue($client, 'refresh_token', '/api/auth/refresh');

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        self::assertArrayNotHasKey('refresh_token', $this->responseCookies($client), 'Tourner le refresh '
            .'token à chaque navigation ferait passer deux onglets parallèles pour un rejeu, ce qui '
            .'invalide toute la famille de jetons (RefreshTokenManager::rotate) — donc déconnecte.');
        self::assertSame($refresh, $this->cookieValue($client, 'refresh_token', '/api/auth/refresh'));
    }

    /** Le mobile porte un Bearer et gère son propre rafraîchissement : l'API ne pose pas de cookie. */
    public function testApiRequestDoesNotRenewTheAccessCookie(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $client->request('GET', '/api/me', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        self::assertArrayNotHasKey('access_token', $this->responseCookies($client));
    }

    /** Une page anonyme ne fabrique évidemment aucune session. */
    public function testAnonymousPageRenewsNothing(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        self::assertArrayNotHasKey('access_token', $this->responseCookies($client));
    }

    /** Garde-fou : le renouvellement ne doit jamais reposer le cookie que la déconnexion efface. */
    public function testLogoutIsNotUndoneByTheRenewal(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $client->request('GET', '/');
        $field = $client->getCrawler()->filter('input[name="_csrf_token"]')->first();
        self::assertGreaterThan(0, $field->count());

        $client->request('POST', '/logout', parameters: ['_csrf_token' => (string) $field->attr('value')], server: self::SAME_ORIGIN);
        self::assertResponseRedirects('/login');
        $cleared = $this->responseCookies($client)['access_token'] ?? null;
        self::assertInstanceOf(Cookie::class, $cleared);
        self::assertEmpty($cleared->getValue());
        self::assertLessThan(Clock::get()->now()->getTimestamp(), $cleared->getExpiresTime());

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    // ───────────────────────── utilitaires ─────────────────────────

    /**
     * @return array<string, Cookie>
     */
    private function responseCookies(KernelBrowser $client): array
    {
        $byName = [];
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            $byName[$cookie->getName()] = $cookie;
        }

        return $byName;
    }

    private function cookieValue(KernelBrowser $client, string $name, string $path = '/'): string
    {
        $cookie = $client->getCookieJar()->get($name, $path);
        self::assertNotNull($cookie, \sprintf('Cookie « %s » absent.', $name));

        return $cookie->getValue();
    }

    /**
     * @return array{iat: int, exp: int}
     */
    private function decode(string $jwt): array
    {
        $encoder = static::getContainer()->get('lexik_jwt_authentication.encoder');
        self::assertInstanceOf(JWTEncoderInterface::class, $encoder);
        $payload = $encoder->decode($jwt);
        self::assertIsInt($payload['iat'] ?? null);
        self::assertIsInt($payload['exp'] ?? null);

        return ['iat' => $payload['iat'], 'exp' => $payload['exp']];
    }

    private function createUser(): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail('sliding@example.com')
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $family = (new Family())->setName('Famille Glissante')->setType(FamilyType::Separated);
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);

        foreach ([$user, $family, $membership] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
    }

    private function login(KernelBrowser $client): void
    {
        $client->request('POST', '/api/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['email' => 'sliding@example.com', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
