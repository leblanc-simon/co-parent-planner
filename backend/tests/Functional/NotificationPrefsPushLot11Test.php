<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Lot 11 US-PUSH-4 — le canal `push` dans `/api/me/notification-prefs` : opt-in (désactivé par
 * défaut), réglable par catégorie, indépendant de l'e-mail, catégorie inconnue rejetée.
 */
final class NotificationPrefsPushLot11Test extends ApiAuthTestCase
{
    public function testPushIsOptInForEveryCategory(): void
    {
        [$client, $token] = $this->authenticatedClient('push-pref-def@example.com');
        $body = $client->request('GET', '/api/me/notification-prefs', ['headers' => $this->bearer($token)])->toArray();

        self::assertSame(
            ['custody' => false, 'agenda' => false, 'expenses' => false, 'exchanges' => false, 'invitation' => false],
            $body['push'],
        );
        self::assertTrue($body['email']['exchanges']); // l'e-mail reste opt-out
    }

    public function testPushToggleIsIndependentFromEmail(): void
    {
        [$client, $token] = $this->authenticatedClient('push-pref-toggle@example.com');
        $h = $this->bearer($token);

        $put = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['push' => ['exchanges' => true], 'email' => ['agenda' => false]],
            'headers' => $h,
        ]);
        self::assertSame(200, $put->getStatusCode());

        $get = $client->request('GET', '/api/me/notification-prefs', ['headers' => $h])->toArray();
        self::assertTrue($get['push']['exchanges']);
        self::assertFalse($get['push']['agenda']);
        self::assertFalse($get['email']['agenda']);
        self::assertTrue($get['email']['exchanges']);

        // Couper le push ne touche pas l'e-mail de la même catégorie.
        $client->request('PUT', '/api/me/notification-prefs', ['json' => ['push' => ['exchanges' => false]], 'headers' => $h]);
        $after = $client->request('GET', '/api/me/notification-prefs', ['headers' => $h])->toArray();
        self::assertFalse($after['push']['exchanges']);
        self::assertTrue($after['email']['exchanges']);
    }

    public function testUnknownPushCategoryRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('push-pref-cat@example.com');
        $h = $this->bearer($token);

        $res = $client->request('PUT', '/api/me/notification-prefs', ['json' => ['push' => ['santé' => true]], 'headers' => $h]);
        self::assertSame(422, $res->getStatusCode());

        $get = $client->request('GET', '/api/me/notification-prefs', ['headers' => $h])->toArray();
        self::assertNotContains(true, $get['push']); // rien n'a été enregistré
    }
}
