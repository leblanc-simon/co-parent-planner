<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Session web **glissante** : chaque navigation authentifiée ré-émet le JWT d'accès (et donc son
 * cookie), si bien que l'expiration se compte depuis la **dernière activité** et non depuis la
 * connexion.
 *
 * Sans ce renouvellement, `ACCESS_TOKEN_TTL` est une durée de vie *absolue* : une personne
 * connectée depuis 59 min était déconnectée au clic suivant, quand bien même elle venait d'agir —
 * et la navigation web (contrairement aux mutations JS, {@see \App\State\Auth\RefreshProcessor} via
 * `api_base.js`) n'a aucun rattrapage : le cookie `refresh_token` est limité au chemin
 * `/api/auth/refresh` et n'accompagne donc pas un simple GET de page. Le TTL devient ici une
 * tolérance d'inactivité (exigence produit : au moins 1 h).
 *
 * Le refresh token n'est **pas** tourné au passage : une rotation par navigation ferait courir un
 * faux positif de réutilisation (onglets parallèles, préchargement Turbo) qui, par construction
 * ({@see \App\Security\Auth\RefreshTokenManager::rotate()}), invaliderait toute la famille de
 * jetons — donc déconnecterait pour de bon. Seul le JWT d'accès, court et sans état, est ré-émis.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 8)]
final class SlidingSessionSubscriber
{
    public function __construct(
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Front web uniquement : le mobile porte un Bearer et gère lui-même son rafraîchissement.
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }
        // Navigations seules. Exclut notamment `POST /logout`, qui doit effacer le cookie, pas le reposer.
        if (!\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }
        // Une réponse qui pose (connexion, refresh) ou efface (déconnexion) déjà les cookies fait autorité.
        if (null !== $request->attributes->get('_auth_tokens')
            || true === $request->attributes->get('_auth_clear_cookies')) {
            return;
        }
        // Seule l'authentification par cookie glisse : un appel porteur d'un en-tête `Authorization`
        // n'a pas de cookie à prolonger.
        $access = $request->cookies->get('access_token');
        if (!\is_string($access) || '' === $access || $request->headers->has('Authorization')) {
            return;
        }
        // Le jeton présenté était valide (sinon le pare-feu n'aurait pas authentifié) : on le prolonge.
        $user = $this->security->getUser();
        if (!$user instanceof User || $event->getResponse()->getStatusCode() >= 400) {
            return;
        }

        $request->attributes->set('_auth_tokens', ['access' => $this->jwtManager->create($user)]);
    }
}
