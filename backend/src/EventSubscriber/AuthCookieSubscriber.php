<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\Clock\Clock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pose / efface les cookies d'auth HttpOnly (décision D2). Le web utilise ces cookies ; le JS ne
 * lit jamais le JWT. Les processors signalent l'action via des attributs de requête.
 *
 * - `_auth_tokens` => ['access' => …, 'refresh' => …] : pose access_token (path /) et
 *    refresh_token (path /api/auth/refresh), HttpOnly, SameSite=Lax, Secure si HTTPS.
 *    La clé `refresh` est **facultative** : la session glissante ({@see SlidingSessionSubscriber})
 *    ne ré-émet que le JWT d'accès, sans faire tourner le refresh token.
 * - `_auth_clear_cookies` => true : efface les deux cookies (déconnexion).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class AuthCookieSubscriber
{
    private const string ACCESS_COOKIE = 'access_token';
    private const string REFRESH_COOKIE = 'refresh_token';
    private const string REFRESH_PATH = '/api/auth/refresh';

    public function __construct(
        #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
        private readonly int $accessTokenTtl,
        #[Autowire('%env(int:REFRESH_TOKEN_TTL)%')]
        private readonly int $refreshTokenTtl,
        #[Autowire('%env(bool:COOKIE_SECURE)%')]
        private readonly bool $forceSecure = false,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        // Secure dès que HTTPS est détecté (proxies de confiance corrects) OU forcé en prod.
        $secure = $this->forceSecure || $request->isSecure();
        $now = Clock::get()->now()->getTimestamp();

        $tokens = $request->attributes->get('_auth_tokens');
        if (\is_array($tokens) && \is_string($tokens['access'] ?? null)) {
            $response->headers->setCookie(
                Cookie::create(self::ACCESS_COOKIE, $tokens['access'], $now + $this->accessTokenTtl)
                    ->withHttpOnly(true)->withSecure($secure)->withSameSite(Cookie::SAMESITE_LAX)->withPath('/'),
            );
            if (\is_string($tokens['refresh'] ?? null)) {
                $response->headers->setCookie(
                    Cookie::create(self::REFRESH_COOKIE, $tokens['refresh'], $now + $this->refreshTokenTtl)
                        ->withHttpOnly(true)->withSecure($secure)->withSameSite(Cookie::SAMESITE_LAX)->withPath(self::REFRESH_PATH),
                );
            }
        }

        if (true === $request->attributes->get('_auth_clear_cookies')) {
            $response->headers->clearCookie(self::ACCESS_COOKIE, '/', null, $secure, true, Cookie::SAMESITE_LAX);
            $response->headers->clearCookie(self::REFRESH_COOKIE, self::REFRESH_PATH, null, $secure, true, Cookie::SAMESITE_LAX);
        }
    }
}
