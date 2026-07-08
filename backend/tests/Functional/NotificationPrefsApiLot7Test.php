<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Lot 7 — préférences de notification (`/api/me/notification-prefs`) : défauts (opt-out), mise à jour
 * de toggle, quiet-hours (valides / invalides / trop longues / effacement), catégorie inconnue.
 * Scope utilisateur (jamais un tiers). Validation RFC 7807.
 */
final class NotificationPrefsApiLot7Test extends ApiAuthTestCase
{
    public function testGetDefaultsAreOptOut(): void
    {
        [$client, $token] = $this->authenticatedClient('np-def@example.com');
        $res = $client->request('GET', '/api/me/notification-prefs', ['headers' => $this->bearer($token)]);
        self::assertSame(200, $res->getStatusCode());
        $body = $res->toArray();

        self::assertTrue($body['email']['custody']);
        self::assertTrue($body['email']['agenda']);
        self::assertTrue($body['email']['exchanges']);
        self::assertTrue($body['email']['invitation']);
        self::assertArrayNotHasKey('expenses', $body['email']); // réservé v2, masqué
        self::assertNull($body['quietHoursStart']);
        self::assertSame('fr-FR', $body['locale']);
    }

    public function testDisableCategoryPersists(): void
    {
        [$client, $token] = $this->authenticatedClient('np-toggle@example.com');
        $h = $this->bearer($token);

        $put = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['email' => ['agenda' => false]],
            'headers' => $h,
        ]);
        self::assertSame(200, $put->getStatusCode());
        self::assertFalse($put->toArray()['email']['agenda']);

        $get = $client->request('GET', '/api/me/notification-prefs', ['headers' => $h])->toArray();
        self::assertFalse($get['email']['agenda']);
        self::assertTrue($get['email']['custody']); // les autres inchangées
    }

    public function testSetAndClearQuietHours(): void
    {
        [$client, $token] = $this->authenticatedClient('np-qh@example.com');
        $h = $this->bearer($token);

        $set = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['quietHoursStart' => '22:00', 'quietHoursEnd' => '07:00'],
            'headers' => $h,
        ]);
        self::assertSame(200, $set->getStatusCode());
        self::assertSame('22:00', $set->toArray()['quietHoursStart']);

        $clear = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['quietHoursStart' => null, 'quietHoursEnd' => null],
            'headers' => $h,
        ]);
        self::assertNull($clear->toArray()['quietHoursStart']);
    }

    public function testInvalidQuietHoursRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('np-bad@example.com');
        $res = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['quietHoursStart' => '25:00', 'quietHoursEnd' => '07:00'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testQuietHoursTooLongRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('np-long@example.com');
        $res = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['quietHoursStart' => '00:00', 'quietHoursEnd' => '23:30'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testIncompleteQuietHoursRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('np-inc@example.com');
        $res = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['quietHoursStart' => '22:00', 'quietHoursEnd' => null],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testUnknownCategoryRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('np-cat@example.com');
        $res = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['email' => ['expenses' => true]],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(422, $res->getStatusCode()); // expenses réservé v2 → rejeté en v1
    }
}
