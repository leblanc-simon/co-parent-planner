<?php

declare(strict_types=1);

namespace App\Web;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;

/**
 * Pose le cookie d'abonnement Mercure (`mercureAuthorization`) d'un écran web.
 *
 * Centralisé ici parce que les quatre écrans temps réel (garde, agenda, enfants, échanges)
 * portaient la même construction copiée-collée, et donc les deux mêmes défauts (revue 2026-08) :
 *
 *  - **C16** — le JWT n'avait ni `iat` ni `exp` (le bundle passe `jwtLifetime = null` à la
 *    fabrique) : la capacité d'écoute était perpétuelle et non révocable, survivant au retrait du
 *    membre de la famille comme à tout changement de droits. Elle est désormais bornée à
 *    {@see self::TTL_SECONDS} ; le rechargement de l'écran la renouvelle, ce qui **revérifie les
 *    droits** à chaque fois.
 *  - **C15** — le topic était accordé sans revérifier le droit du membre sur le domaine. C'est
 *    l'appelant qui décide, mais la liste vide est ici explicitement gérée : aucun droit ⇒ **aucun
 *    cookie**, comme `/api/realtime/token` le fait déjà.
 *
 * Le serveur reste seule autorité sur les claims `subscribe` ; `publish` est toujours vide (le
 * client ne publie jamais).
 *
 * **Domaine du cookie** (`MERCURE_COOKIE_DOMAIN`) : par défaut le cookie ne vaut que pour l'hôte
 * de l'app, ce qui suffit quand le hub est servi sous le même hôte (`/.well-known/mercure`). Quand
 * le hub vit sur un **autre sous-domaine** (`hub.example.org` pour `app.example.org`), le
 * navigateur ne lui enverrait jamais le cookie et l'écran afficherait « mises à jour en direct
 * indisponibles » : il faut alors un domaine parent commun (`.example.org`). Le domaine le plus
 * étroit qui couvre les deux hôtes — chaque hôte couvert reçoit une capacité d'écoute.
 */
final class MercureSubscription
{
    /** Durée de vie de l'abonnement : au-delà, le navigateur doit repasser par le serveur. */
    public const int TTL_SECONDS = 3600;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly ClockInterface $clock,
        #[Autowire('%env(default::MERCURE_COOKIE_DOMAIN)%')]
        private readonly ?string $cookieDomain = null,
    ) {
    }

    /**
     * @param list<string> $topics topics autorisés en lecture — vide = pas d'abonnement du tout
     */
    public function attach(Response $response, array $topics): void
    {
        $factory = $this->hub->getFactory();
        if (null === $factory || [] === $topics) {
            return;
        }

        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval('PT'.self::TTL_SECONDS.'S'));
        $jwt = $factory->create($topics, [], ['iat' => $now, 'exp' => $expiresAt]);

        $response->headers->setCookie(
            Cookie::create('mercureAuthorization', $jwt)
                ->withHttpOnly(true)
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/.well-known/mercure')
                ->withDomain(null === $this->cookieDomain || '' === $this->cookieDomain ? null : $this->cookieDomain)
                // Le cookie ne survit pas au JWT qu'il transporte : un cookie encore présent mais
                // périmé ne produirait qu'un abonnement refusé sans explication.
                ->withExpires($expiresAt),
        );
    }
}
