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

        // Le serveur est seule autorité sur subscribe ; sans famille active, aucun droit : le jeton
        // d'accès (RFC 9068, Mercure 1.0) ne porte aucun `authorization_details`.
        $payload = $this->decodePayload($body['token']);
        self::assertArrayNotHasKey('authorization_details', $payload);
        self::assertArrayNotHasKey('mercure', $payload, 'Claim du protocole 0.x, refusé par un hub 1.0.');
        foreach (['iss', 'aud', 'sub', 'client_id', 'iat', 'exp', 'jti'] as $claim) {
            self::assertArrayHasKey($claim, $payload, "Claim RFC 9068 requis par le hub : $claim.");
        }
    }

    public function testScopedByActiveFamilyIncludesNotesTopicFromLot2(): void
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

        // Lot 2/3/4/6 : un parent (children_notes + custody + agenda + exchanges ≥ view) sur une famille
        // séparée reçoit les topics notes, garde, agenda ET échanges (liste + détail), scopés à sa
        // famille active (US-CH-3, US-CUST-5, US-AG-8, US-EX-2/7).
        // Le détail d'échange passe par un URL Pattern (`:exchangeId`) : Mercure 1.0 a retiré les
        // gabarits RFC 6570.
        $payload = $this->decodePayload($response->toArray()['token']);
        self::assertSame([[
            'type' => 'https://mercure.rocks/authorization-detail',
            'actions' => ['subscribe'],
            'topics' => [
                ['match' => '/families/'.$familyId.'/notes'],
                ['match' => '/families/'.$familyId.'/custody'],
                ['match' => '/families/'.$familyId.'/agenda'],
                ['match' => '/families/'.$familyId.'/exchanges'],
                ['match' => '/families/'.$familyId.'/exchanges/:exchangeId', 'match_type' => 'urlpattern'],
            ],
        ]], $payload['authorization_details'] ?? null);
    }
}
