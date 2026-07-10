<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\IdempotencyKey;
use App\Entity\User;
use App\Enum\IdempotencyStatus;
use App\Exception\IdempotencyConflictException;
use App\Repository\IdempotencyKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Middleware d'idempotence serveur (cdc §6.1, Lot 8 §3.1/§6.3) — le seul chemin transverse pour les
 * mutations rejouées par l'outbox mobile.
 *
 * Sur un `POST /api/*` (hors `/api/auth`) portant l'en-tête `Idempotency-Key`, scopé à
 * `(user, endpoint, clé)` + empreinte du payload :
 *  - clé déjà `completed` → **rejoue** la réponse mémorisée (même code + corps), le contrôleur ne
 *    s'exécute pas (aucun doublon d'effet) ;
 *  - clé `in_progress` (concurrente) ou clé réutilisée pour une requête différente → **409** (§4.2) ;
 *  - clé neuve → enregistre `in_progress` (sous verrou), laisse le contrôleur s'exécuter, puis mémorise
 *    la réponse (< 500) ou libère la clé (5xx, pour permettre un retry).
 *
 * Le corps mémorisé est **chiffré au repos** (§4.8) via {@see IdempotencyKey::$responseBody}.
 */
final class IdempotencySubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    private const string HEADER = 'Idempotency-Key';
    private const string OWNED_ROW = '_idempotency_row_id';
    /** Au-delà de ce délai, une entrée `in_progress` est réputée abandonnée (worker mort) et réclamable. */
    private const int IN_PROGRESS_LEASE_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IdempotencyKeyRepository $repository,
        private readonly Security $security,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Après le firewall (l'utilisateur doit être authentifié), avant le contrôleur.
            KernelEvents::REQUEST => ['onRequest', 4],
            // Avant AuthCookieSubscriber peu importe : on ne touche pas aux cookies.
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $key = $this->applicableKey($request);
        if (null === $key) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return; // mutation non authentifiée : pas d'idempotence (ne devrait pas arriver sous ^/api)
        }

        $method = $request->getMethod();
        $endpoint = $request->getPathInfo();
        $familyId = $this->familyId($request);
        $payloadHash = hash('sha256', ($request->getQueryString() ?? '')."\n".$request->getContent());

        $conflict = false;
        $replay = null;
        $created = null;

        $this->em->wrapInTransaction(function () use ($user, $key, $method, $endpoint, $familyId, $payloadHash, &$conflict, &$replay, &$created): void {
            // Sérialise les requêtes concurrentes de même (user, clé).
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                ['k' => 'idem:'.$user->getUserIdentifier().':'.$key],
            );

            $existing = $this->repository->findOneByUserAndKey($user, $key);
            if (null !== $existing) {
                if (IdempotencyStatus::Completed === $existing->getStatus()) {
                    // Rejeu d'une mutation aboutie : servir la réponse mémorisée si le scope+payload
                    // correspond, sinon clé réutilisée pour une autre requête → 409 (§4.2).
                    if ($existing->matches($method, $endpoint, $familyId, $payloadHash)) {
                        $replay = $existing;
                    } else {
                        $conflict = true;
                    }

                    return;
                }

                // in_progress : concurrente vivante → 409 ; mais si trop ancienne (worker mort avant
                // onResponse), on la réclame pour ne pas bloquer la clé jusqu'au TTL (48 h).
                $lease = Clock::get()->now()->sub(new \DateInterval('PT'.self::IN_PROGRESS_LEASE_SECONDS.'S'));
                if ($existing->getCreatedAt() > $lease) {
                    $conflict = true;

                    return;
                }
                $this->em->remove($existing);
                $this->em->flush(); // exécute le DELETE avant la réinsertion (contrainte unique)
            }

            $created = new IdempotencyKey($user, $key, $method, $endpoint, $familyId, $payloadHash);
            $this->em->persist($created);
        });

        if ($conflict) {
            throw new IdempotencyConflictException();
        }
        if ($replay instanceof IdempotencyKey) {
            $event->setResponse($this->replay($replay));

            return;
        }
        if ($created instanceof IdempotencyKey) {
            $request->attributes->set(self::OWNED_ROW, (string) $created->getId());
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $rowId = $event->getRequest()->attributes->get(self::OWNED_ROW);
        if (!\is_string($rowId) || !Uuid::isValid($rowId)) {
            return;
        }

        $row = $this->repository->find(Uuid::fromString($rowId));
        if (!$row instanceof IdempotencyKey) {
            return;
        }

        $response = $event->getResponse();
        $code = $response->getStatusCode();
        if ($code < 200 || $code >= 300) {
            // SEULES les réponses de succès (2xx) sont mémorisables. Toute erreur — 4xx transitoire
            // (429 rate-limit, 403 step-up, 409, 404 de réplication…) ou corrigeable (422), et 5xx —
            // LIBÈRE la clé : sinon une erreur passagère figerait la mutation pour tout le TTL et
            // l'outbox rejouerait l'erreur au lieu d'aboutir (perte de donnée silencieuse, §4.3).
            $this->em->remove($row);
            $this->em->flush();

            return;
        }

        $row->complete($code, (string) $response->getContent(), $response->headers->get('Content-Type'));
        $this->em->flush();
    }

    /** Clé applicable (POST /api hors /api/auth avec en-tête non vide), sinon null. */
    private function applicableKey(Request $request): ?string
    {
        if ('POST' !== $request->getMethod()) {
            return null;
        }
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/') || str_starts_with($path, '/api/auth/')) {
            return null;
        }
        $key = $request->headers->get(self::HEADER);

        return \is_string($key) && '' !== $key ? $key : null;
    }

    private function familyId(Request $request): ?string
    {
        $value = $request->headers->get('X-Family-Id');

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function replay(IdempotencyKey $row): Response
    {
        $response = new Response(
            $row->getResponseBody() ?? '',
            $row->getResponseCode() ?? Response::HTTP_OK,
        );
        if (null !== $row->getResponseContentType()) {
            $response->headers->set('Content-Type', $row->getResponseContentType());
        }
        $response->headers->set('Idempotent-Replayed', 'true');

        return $response;
    }
}
