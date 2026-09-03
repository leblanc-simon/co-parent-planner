<?php

declare(strict_types=1);

namespace App\Web;

use Symfony\Component\HttpFoundation\Request;

/**
 * Cible à retrouver après connexion (« next »).
 *
 * Les liens des e-mails de notification pointent une page précise
 * (`/echanges?exchange=…`, `/garde`, `/agenda`). Ouverts depuis une session
 * expirée — le cas le plus fréquent, un e-mail se lit rarement dans la minute —,
 * ils menaient à `/login` **sans conserver la destination**, puis à l'accueil :
 * la notification perdait sa cible, et l'utilisateur devait retrouver à la main
 * l'échange dont on venait de lui parler (revue web 2026-07, constat 6.8).
 *
 * La cible n'est acceptée que **relative au site** : un `next` fourni par un tiers
 * ne doit jamais servir de tremplin vers un domaine externe (redirection ouverte).
 */
final class NextTarget
{
    /** Longueur au-delà de laquelle la valeur n'est plus une URL de l'application. */
    private const int MAX_LENGTH = 2000;

    /**
     * Cible interne sûre, ou `null` si la valeur est absente, hors-site ou inexploitable.
     */
    public static function sanitize(?string $next): ?string
    {
        if (null === $next || '' === $next || \strlen($next) > self::MAX_LENGTH) {
            return null;
        }
        // Caractères de contrôle : découpage d'en-tête, et jamais présents dans une URL légitime.
        if (1 === preg_match('/[\x00-\x1F\x7F]/', $next)) {
            return null;
        }
        // Seul un chemin absolu du site est accepté. `//host` et `/\host` sont des URL
        // relatives au protocole : le navigateur les résout vers un domaine externe.
        if (!str_starts_with($next, '/')
            || str_starts_with($next, '//')
            || str_starts_with($next, '/\\')) {
            return null;
        }

        return $next;
    }

    /**
     * Page demandée par la requête courante, à conserver le temps de la connexion.
     *
     * Les écrans d'authentification eux-mêmes ne sont pas des cibles : y revenir
     * après s'être connecté n'aurait aucun sens.
     */
    public static function of(Request $request): ?string
    {
        $uri = self::sanitize($request->getRequestUri());
        if (null === $uri) {
            return null;
        }
        foreach (['/login', '/register', '/forgot-password', '/reset-password', '/verify-email'] as $authPath) {
            if ($uri === $authPath || str_starts_with($uri, $authPath.'?')) {
                return null;
            }
        }

        return $uri;
    }

    /**
     * Paramètres de route pour rediriger vers la connexion en gardant la cible.
     *
     * @return array<string, string>
     */
    public static function loginParams(Request $request): array
    {
        $next = self::of($request);

        return null === $next ? [] : ['next' => $next];
    }
}
