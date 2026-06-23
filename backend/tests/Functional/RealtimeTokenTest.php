<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Tâche 9 / §3.12 — émission d'un JWT Mercure scopé par famille active, claims `subscribe`
 * **vides au Lot 0** (aucun topic métier), recalculés à la bascule de famille.
 */
final class RealtimeTokenTest extends ApiAuthTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts, 'JWT mal formé');
        $b64 = strtr($parts[1], '-_', '+/');
        $b64 .= str_repeat('=', (4 - \strlen($b64) % 4) % 4);
        $json = base64_decode($b64, true);
        self::assertIsString($json);
        $payload = json_decode($json, true);
        self::assertIsArray($payload);

        return $payload;
    }

    public function testRequiresAuthentication(): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/api/realtime/token', ['headers' => ['Accept' => 'application/json']]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testEmitsTokenWithEmptySubscribe(): void
    {
        [$client, $token] = $this->authenticatedClient('rt@example.com');

        $response = $client->request('GET', '/api/realtime/token', ['headers' => $this->bearer($token)]);
        self::assertSame(200, $response->getStatusCode());

        $body = $response->toArray();
        self::assertNotEmpty($body['token']);
        self::assertNotEmpty($body['hub']);

        // Le serveur est seule autorité sur subscribe ; au Lot 0 il est vide.
        $payload = $this->decodePayload($body['token']);
        self::assertArrayHasKey('mercure', $payload);
        self::assertSame([], $payload['mercure']['subscribe'] ?? null);
        self::assertSame([], $payload['mercure']['publish'] ?? null);
    }

    public function testScopedByActiveFamilyStaysEmptyAtLot0(): void
    {
        [$client, $token] = $this->authenticatedClient('rt2@example.com');

        $created = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Foyer', 'type' => 'separated'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(201, $created->getStatusCode());
        $familyId = $created->toArray()['id'];

        $response = $client->request('GET', '/api/realtime/token', [
            'headers' => $this->bearer($token) + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodePayload($response->toArray()['token']);
        self::assertSame([], $payload['mercure']['subscribe'] ?? null);
    }
}
