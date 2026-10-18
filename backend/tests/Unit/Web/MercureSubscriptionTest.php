<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use App\Web\MercureSubscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

/**
 * Domaine du cookie d'abonnement web (`MERCURE_COOKIE_DOMAIN`) : sans valeur, le cookie reste
 * attaché à l'hôte de l'app ; avec un domaine parent, il atteint un hub servi sur un autre
 * sous-domaine — sans quoi le navigateur ne le lui envoie jamais.
 */
#[CoversClass(MercureSubscription::class)]
final class MercureSubscriptionTest extends TestCase
{
    private function cookie(?string $domain, ?string $cookieName = 'mercure_access_token', string $hubUrl = 'https://hub.example.org/.well-known/mercure'): Cookie
    {
        $hub = new MockHub(
            $hubUrl,
            new StaticTokenProvider('jeton'),
            static fn (Update $u): string => 'id',
            // Claims RFC 9068 que le bundle ajoute à la fabrique (mercure.yaml).
            new DefaultClaimsTokenFactory(new LcobucciFactory(str_repeat('s', 32)), [
                'iss' => 'https://app.example.org', 'aud' => $hubUrl, 'sub' => 'cpp', 'client_id' => 'cpp',
            ]),
            cookieName: $cookieName,
        );
        $response = new Response();
        (new MercureSubscription($hub, new MockClock(), $domain))->attach($response, ['/families/f1/exchanges']);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }

    public function testCookieStaysOnAppHostByDefault(): void
    {
        self::assertNull($this->cookie(null)->getDomain());
        self::assertNull($this->cookie('')->getDomain());
    }

    public function testCookieCoversConfiguredParentDomain(): void
    {
        $cookie = $this->cookie('.example.org');

        self::assertSame('.example.org', $cookie->getDomain());
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
    }

    public function testCookieNameFollowsHub(): void
    {
        self::assertSame('mercure_access_token', $this->cookie(null)->getName());
    }

    /** Un cookie `__Secure-` sans l'attribut Secure est rejeté par le navigateur. */
    public function testSecurePrefixedCookieIsSecure(): void
    {
        $cookie = $this->cookie(null, '__Secure-mercure_access_token');

        self::assertSame('__Secure-mercure_access_token', $cookie->getName());
        self::assertTrue($cookie->isSecure());
    }

    public function testPlainHttpHubKeepsCookieUsableOverHttp(): void
    {
        self::assertFalse($this->cookie(null, hubUrl: 'http://127.0.0.1:3000/.well-known/mercure')->isSecure());
    }

    /** Jeton d'accès Mercure 1.0 : `authorization_details`, plus de claim `mercure`. */
    public function testTokenCarriesSubscribeGrant(): void
    {
        $parts = explode('.', $this->cookie(null)->getValue() ?? '');
        $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([[
            'type' => 'https://mercure.rocks/authorization-detail',
            'actions' => ['subscribe'],
            'topics' => [['match' => '/families/f1/exchanges']],
        ]], $claims['authorization_details']);
        self::assertArrayNotHasKey('mercure', $claims);
    }
}
