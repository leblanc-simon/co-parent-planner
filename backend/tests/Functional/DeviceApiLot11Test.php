<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\DeviceToken;
use App\Enum\AuditAction;
use App\Enum\DeviceTokenStatus;
use App\Repository\DeviceTokenRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 11 — appareils enregistrés pour le push (`/api/me/devices`) : enregistrement idempotent,
 * réassignation d'un jeton à un autre compte, rotation par installation, révocation, validation
 * RFC 7807, 401, cloisonnement entre comptes (404 non énumérant), jeton jamais exposé ni stocké en
 * clair. Ressource utilisateur : aucun `X-Family-Id` n'est envoyé dans ce fichier.
 */
final class DeviceApiLot11Test extends ApiAuthTestCase
{
    private const string TOKEN = 'fcm-Token_AbC:123-appareil-de-test';

    private function devices(): DeviceTokenRepository
    {
        $repo = self::getContainer()->get(DeviceTokenRepository::class);
        self::assertInstanceOf(DeviceTokenRepository::class, $repo);

        return $repo;
    }

    /** @return list<DeviceToken> */
    private function rowsFor(string $token): array
    {
        $this->em()->clear();

        return array_values(array_filter(
            $this->devices()->findAll(),
            static fn (DeviceToken $d): bool => $d->getToken() === $token,
        ));
    }

    public function testRegisterCreatesActiveDeviceWithoutExposingToken(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-create@example.com');

        $res = $client->request('POST', '/api/me/devices', [
            'json' => ['platform' => 'ios', 'token' => self::TOKEN, 'installationId' => 'inst-1', 'appVersion' => '2.0.0', 'deviceModel' => 'iPhone 15', 'locale' => 'fr-FR'],
            'headers' => $this->bearer($token),
        ]);

        self::assertSame(201, $res->getStatusCode());
        $body = $res->toArray();
        self::assertSame('ios', $body['platform']);
        self::assertSame('active', $body['status']);
        self::assertSame('iPhone 15', $body['deviceModel']);
        self::assertArrayNotHasKey('token', $body);
        self::assertStringNotContainsString(self::TOKEN, $res->getContent());

        $rows = $this->rowsFor(self::TOKEN);
        self::assertCount(1, $rows);
        self::assertSame(DeviceTokenStatus::Active, $rows[0]->getStatus());
        self::assertSame('inst-1', $rows[0]->getInstallationId());
        self::assertNotNull($rows[0]->getLastSeenAt());
    }

    /** Un dump SQL brut ne révèle pas les jetons (🔒, Lot 11 §5.4) — ni l'empreinte, ni l'audit. */
    public function testTokenIsCiphertextAtRestAndAbsentFromAudit(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-crypto@example.com');
        $id = $client->request('POST', '/api/me/devices', [
            'json' => ['platform' => 'android', 'token' => self::TOKEN],
            'headers' => $this->bearer($token),
        ])->toArray()['id'];

        $conn = $this->em()->getConnection();
        $raw = $conn->fetchAssociative('SELECT token, token_hash FROM device_tokens WHERE id = :id', ['id' => $id]);
        self::assertIsArray($raw);
        self::assertStringNotContainsString(self::TOKEN, (string) $raw['token']);
        self::assertNotSame(self::TOKEN, $raw['token_hash']);

        $audit = $conn->fetchAllAssociative('SELECT metadata::text AS m FROM audit_logs WHERE action = :a', ['a' => AuditAction::DeviceRegistered->value]);
        self::assertNotEmpty($audit);
        foreach ($audit as $row) {
            self::assertStringNotContainsString(self::TOKEN, (string) $row['m']);
        }
    }

    public function testReRegisteringSameTokenIsIdempotent(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-idem@example.com');
        $h = $this->bearer($token);

        $first = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'android', 'token' => self::TOKEN, 'appVersion' => '1.0.0'], 'headers' => $h])->toArray();
        $second = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'android', 'token' => self::TOKEN, 'appVersion' => '1.1.0'], 'headers' => $h]);

        self::assertSame(201, $second->getStatusCode());
        self::assertSame($first['id'], $second->toArray()['id']);
        self::assertSame('1.1.0', $second->toArray()['appVersion']); // mise à jour, pas de doublon
        self::assertCount(1, $this->rowsFor(self::TOKEN));
    }

    /** Rejeu de l'outbox mobile : `Idempotency-Key` renvoie la réponse mémorisée (Lot 11 §6.1). */
    public function testIdempotencyKeyReplaysRegistration(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-idemkey@example.com');
        $h = $this->bearer($token) + ['Idempotency-Key' => 'outbox-device-1'];
        $payload = ['platform' => 'android', 'token' => self::TOKEN];

        $first = $client->request('POST', '/api/me/devices', ['json' => $payload, 'headers' => $h]);
        $replay = $client->request('POST', '/api/me/devices', ['json' => $payload, 'headers' => $h]);

        self::assertSame(201, $replay->getStatusCode());
        self::assertSame('true', $replay->getHeaders()['idempotent-replayed'][0] ?? null);
        self::assertSame($first->toArray()['id'], $replay->toArray()['id']);
        self::assertCount(1, $this->rowsFor(self::TOKEN));
    }

    /** Téléphone passé d'un compte à l'autre : le push ne doit jamais atteindre l'ancien compte. */
    public function testSameTokenOnAnotherAccountRevokesPreviousOwner(): void
    {
        [$clientA, $tokenA] = $this->authenticatedClient('dev-owner-a@example.com');
        $idA = $clientA->request('POST', '/api/me/devices', ['json' => ['platform' => 'ios', 'token' => self::TOKEN], 'headers' => $this->bearer($tokenA)])->toArray()['id'];

        $this->createActiveUser('dev-owner-b@example.com');
        $tokenB = $this->login($clientA, 'dev-owner-b@example.com');
        $idB = $clientA->request('POST', '/api/me/devices', ['json' => ['platform' => 'ios', 'token' => self::TOKEN], 'headers' => $this->bearer($tokenB)])->toArray()['id'];

        self::assertNotSame($idA, $idB);
        $byId = [];
        foreach ($this->rowsFor(self::TOKEN) as $row) {
            $byId[(string) $row->getId()] = $row;
        }
        self::assertSame(DeviceTokenStatus::Revoked, $byId[$idA]->getStatus());
        self::assertSame(DeviceTokenStatus::Active, $byId[$idB]->getStatus());
        self::assertSame('dev-owner-b@example.com', $byId[$idB]->getUser()->getEmail());

        $listA = $clientA->request('GET', '/api/me/devices', ['headers' => $this->bearer($tokenA)])->toArray();
        self::assertSame([], $listA['member'] ?? $listA);
        self::assertSame(1, $this->em()->getRepository(AuditLog::class)->count(['action' => AuditAction::DeviceReassigned]));
    }

    /** Rotation du jeton par l'OS : un seul jeton actif par installation (US-PUSH-2). */
    public function testTokenRotationOnSameInstallationRevokesOldToken(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-rotate@example.com');
        $h = $this->bearer($token);

        $old = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'android', 'token' => 'ancien-jeton', 'installationId' => 'inst-rot'], 'headers' => $h])->toArray()['id'];
        $new = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'android', 'token' => 'nouveau-jeton', 'installationId' => 'inst-rot'], 'headers' => $h])->toArray()['id'];

        self::assertNotSame($old, $new);
        self::assertSame(DeviceTokenStatus::Revoked, $this->rowsFor('ancien-jeton')[0]->getStatus());
        self::assertSame(DeviceTokenStatus::Active, $this->rowsFor('nouveau-jeton')[0]->getStatus());

        $list = $client->request('GET', '/api/me/devices', ['headers' => $h])->toArray();
        $ids = array_column($list['member'] ?? $list, 'id');
        self::assertSame([$new], $ids);
    }

    public function testInvalidPayloadsAreRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-invalid@example.com');
        $h = $this->bearer($token);

        foreach ([
            ['platform' => 'windows', 'token' => self::TOKEN],
            ['platform' => 'ios', 'token' => ''],
            ['platform' => 'ios'],
            ['platform' => 'ios', 'token' => str_repeat('x', 4097)],
            ['platform' => 'ios', 'token' => self::TOKEN, 'locale' => 'pas une langue'],
        ] as $payload) {
            $res = $client->request('POST', '/api/me/devices', ['json' => $payload, 'headers' => $h]);
            self::assertSame(422, $res->getStatusCode(), json_encode($payload, \JSON_THROW_ON_ERROR));
            self::assertStringContainsString('application/problem+json', $res->getHeaders(false)['content-type'][0] ?? '');
        }
        self::assertSame(0, $this->devices()->count([]));
    }

    public function testPlatformErrorIsLocalized(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-i18n@example.com');
        $res = $client->request('POST', '/api/me/devices', [
            'json' => ['platform' => 'windows', 'token' => self::TOKEN],
            'headers' => $this->bearer($token),
        ]);

        self::assertStringContainsString('« ios » ou « android »', (string) ($res->toArray(false)['detail'] ?? ''));
    }

    public function testAnonymousRequestsAreRejected(): void
    {
        $client = self::createClient();

        self::assertSame(401, $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'ios', 'token' => self::TOKEN]])->getStatusCode());
        self::assertSame(401, $client->request('GET', '/api/me/devices')->getStatusCode());
        self::assertSame(401, $client->request('DELETE', '/api/me/devices/'.Uuid::v7())->getStatusCode());
    }

    public function testRevokeOwnDeviceStopsListingIt(): void
    {
        [$client, $token] = $this->authenticatedClient('dev-revoke@example.com');
        $h = $this->bearer($token);
        $id = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'ios', 'token' => self::TOKEN], 'headers' => $h])->toArray()['id'];

        self::assertSame(204, $client->request('DELETE', '/api/me/devices/'.$id, ['headers' => $h])->getStatusCode());
        // Idempotent : une seconde révocation ne change rien.
        self::assertSame(204, $client->request('DELETE', '/api/me/devices/'.$id, ['headers' => $h])->getStatusCode());

        $list = $client->request('GET', '/api/me/devices', ['headers' => $h])->toArray();
        self::assertSame([], $list['member'] ?? $list);
        $row = $this->rowsFor(self::TOKEN)[0];
        self::assertSame(DeviceTokenStatus::Revoked, $row->getStatus());
        self::assertNotNull($row->getRevokedAt());
        self::assertSame(1, $this->em()->getRepository(AuditLog::class)->count(['action' => AuditAction::DeviceRevoked]));
    }

    public function testCannotSeeOrRevokeAnotherUsersDevice(): void
    {
        [$client, $tokenA] = $this->authenticatedClient('dev-iso-a@example.com');
        $idA = $client->request('POST', '/api/me/devices', ['json' => ['platform' => 'ios', 'token' => self::TOKEN], 'headers' => $this->bearer($tokenA)])->toArray()['id'];

        $this->createActiveUser('dev-iso-b@example.com');
        $tokenB = $this->login($client, 'dev-iso-b@example.com');
        $hB = $this->bearer($tokenB);

        $res = $client->request('DELETE', '/api/me/devices/'.$idA, ['headers' => $hB]);
        self::assertSame(404, $res->getStatusCode()); // ne révèle pas l'existence
        self::assertSame(404, $client->request('DELETE', '/api/me/devices/'.Uuid::v7(), ['headers' => $hB])->getStatusCode());
        self::assertSame(404, $client->request('DELETE', '/api/me/devices/pas-un-uuid', ['headers' => $hB])->getStatusCode());

        $listB = $client->request('GET', '/api/me/devices', ['headers' => $hB])->toArray();
        self::assertSame([], $listB['member'] ?? $listB);
        self::assertSame(DeviceTokenStatus::Active, $this->rowsFor(self::TOKEN)[0]->getStatus());
    }
}
