<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Idempotence serveur des POST mutables (cdc §6.1, Lot 8 §3.1/§6.3) : dédoublonnage par clé scopée,
 * rejeu de la réponse mémorisée, détection d'incohérence de payload (§4.2), cloisonnement par utilisateur.
 */
final class IdempotencyTest extends ApiAuthTestCase
{
    public function testSameKeyReplaysResponseWithoutDoubleEffect(): void
    {
        [$client, $token] = $this->authenticatedClient('idem@example.com');
        $headers = $this->bearer($token) + ['Idempotency-Key' => 'idem-key-1'];

        $first = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille A', 'type' => 'separated'],
            'headers' => $headers,
        ]);
        self::assertSame(201, $first->getStatusCode());
        $id = $first->toArray()['id'];

        // Rejeu (même clé, même payload) : réponse mémorisée renvoyée, contrôleur NON ré-exécuté.
        $replay = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille A', 'type' => 'separated'],
            'headers' => $headers,
        ]);
        self::assertSame(201, $replay->getStatusCode());
        self::assertSame($id, $replay->toArray()['id']);
        self::assertSame('true', $replay->getHeaders()['idempotent-replayed'][0] ?? null);

        // Aucun doublon : une seule famille créée.
        $list = $client->request('GET', '/api/families', ['headers' => $this->bearer($token)]);
        self::assertCount(1, $list->toArray());
    }

    public function testSameKeyDifferentPayloadIsRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('idem2@example.com');
        $headers = $this->bearer($token) + ['Idempotency-Key' => 'shared-key'];

        self::assertSame(201, $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille A', 'type' => 'separated'],
            'headers' => $headers,
        ])->getStatusCode());

        // Même clé, payload différent → 409 (jamais de réponse mémorisée servie à tort, §4.2).
        $conflict = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille B', 'type' => 'separated'],
            'headers' => $headers,
        ]);
        self::assertSame(409, $conflict->getStatusCode());
    }

    public function testKeyIsScopedPerUser(): void
    {
        [$client, $tokenA] = $this->authenticatedClient('owner-a@example.com');
        $this->createActiveUser('owner-b@example.com');
        $tokenB = $this->login($client, 'owner-b@example.com');

        // Même clé pour deux utilisateurs distincts : indépendantes (aucun rejeu croisé).
        self::assertSame(201, $client->request('POST', '/api/families', [
            'json' => ['name' => 'A', 'type' => 'separated'],
            'headers' => $this->bearer($tokenA) + ['Idempotency-Key' => 'k'],
        ])->getStatusCode());

        $b = $client->request('POST', '/api/families', [
            'json' => ['name' => 'B', 'type' => 'separated'],
            'headers' => $this->bearer($tokenB) + ['Idempotency-Key' => 'k'],
        ]);
        self::assertSame(201, $b->getStatusCode());
        self::assertSame('B', $b->toArray()['name']);
    }

    public function testErrorResponseDoesNotBurnTheKey(): void
    {
        [$client, $token] = $this->authenticatedClient('idem4@example.com');
        $headers = $this->bearer($token) + ['Idempotency-Key' => 'retry-key'];

        // 1er essai invalide → 422 : la clé n'est PAS mémorisée (pas de gel de mutation).
        $invalid = $client->request('POST', '/api/families', [
            'json' => ['name' => 'X', 'type' => 'inexistant'],
            'headers' => $headers,
        ]);
        self::assertSame(422, $invalid->getStatusCode());

        // 2e essai corrigé (même clé) : aboutit (la clé libérée autorise le rejeu), pas de 409/rejeu d'erreur.
        $ok = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Corrigée', 'type' => 'separated'],
            'headers' => $headers,
        ]);
        self::assertSame(201, $ok->getStatusCode());
        self::assertSame('Corrigée', $ok->toArray()['name']);
    }

    public function testDifferentKeyCreatesDistinctResources(): void
    {
        [$client, $token] = $this->authenticatedClient('idem3@example.com');

        $client->request('POST', '/api/families', [
            'json' => ['name' => 'Un', 'type' => 'separated'],
            'headers' => $this->bearer($token) + ['Idempotency-Key' => 'key-un'],
        ]);
        $client->request('POST', '/api/families', [
            'json' => ['name' => 'Deux', 'type' => 'separated'],
            'headers' => $this->bearer($token) + ['Idempotency-Key' => 'key-deux'],
        ]);

        $list = $client->request('GET', '/api/families', ['headers' => $this->bearer($token)]);
        self::assertCount(2, $list->toArray());
    }
}
