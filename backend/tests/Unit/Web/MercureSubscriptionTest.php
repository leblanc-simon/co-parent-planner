<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use App\Web\MercureSubscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
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
    private function cookie(?string $domain): Cookie
    {
        $hub = new MockHub(
            'https://hub.example.org/.well-known/mercure',
            new StaticTokenProvider('jeton'),
            static fn (Update $u): string => 'id',
            new LcobucciFactory(str_repeat('s', 32)),
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
}
