<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 1 — liens entre familles (US-LINK-1/2/3) : proposer (pending), activer (destinataire),
 * lister sans données internes, auto-lien (422), doublon bidirectionnel (409), déliaison.
 */
final class FamilyLinkLot1Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} client, bearer, family id */
    private function adminWithFamily(string $email, string $familyName): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => $familyName, 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    public function testFullLinkLifecycle(): void
    {
        [$clientA, $authA, $f1] = $this->adminWithFamily('linkA@example.com', 'Famille A');
        [$clientB, $authB, $f2] = $this->adminWithFamily('linkB@example.com', 'Famille B');

        // A propose le lien F1 -> F2.
        $create = $clientA->request('POST', '/api/families/'.$f1.'/links', [
            'json' => ['linkedFamilyId' => $f2],
            'headers' => $authA + ['X-Family-Id' => $f1],
        ]);
        self::assertSame(201, $create->getStatusCode());
        $link = $create->toArray();
        self::assertSame('pending', $link['status']);
        self::assertSame($f2, $link['otherFamilyId']);
        self::assertSame('Famille B', $link['otherFamilyName']);
        self::assertTrue($link['initiatedByMe']);

        // B voit le lien entrant (pending), du point de vue F2.
        $listB = $clientB->request('GET', '/api/families/'.$f2.'/links', [
            'headers' => $authB + ['X-Family-Id' => $f2],
        ])->toArray();
        self::assertCount(1, $listB);
        self::assertFalse($listB[0]['initiatedByMe']);
        self::assertSame('Famille A', $listB[0]['otherFamilyName']);
        $linkId = $listB[0]['id'];

        // B (destinataire) active.
        $activate = $clientB->request('POST', '/api/families/'.$f2.'/links/'.$linkId.'/activate', [
            'headers' => $authB + ['X-Family-Id' => $f2],
        ]);
        self::assertSame(200, $activate->getStatusCode());
        self::assertSame('active', $activate->toArray()['status']);

        // Déliaison par A (effet bidirectionnel).
        $delete = $clientA->request('DELETE', '/api/families/'.$f1.'/links/'.$linkId, [
            'headers' => $authA + ['X-Family-Id' => $f1],
        ]);
        self::assertSame(204, $delete->getStatusCode());

        $listAfter = $clientB->request('GET', '/api/families/'.$f2.'/links', [
            'headers' => $authB + ['X-Family-Id' => $f2],
        ])->toArray();
        self::assertCount(0, $listAfter);
    }

    public function testSelfLinkForbidden(): void
    {
        [$client, $auth, $f1] = $this->adminWithFamily('selflink@example.com', 'Famille Solo');

        $response = $client->request('POST', '/api/families/'.$f1.'/links', [
            'json' => ['linkedFamilyId' => $f1],
            'headers' => $auth + ['X-Family-Id' => $f1],
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('/errors/self-link-forbidden', $response->toArray(false)['type']);
    }

    public function testDuplicateLinkConflictsRegardlessOfDirection(): void
    {
        [$clientA, $authA, $f1] = $this->adminWithFamily('dupA@example.com', 'Dup A');
        [$clientB, $authB, $f2] = $this->adminWithFamily('dupB@example.com', 'Dup B');

        $clientA->request('POST', '/api/families/'.$f1.'/links', [
            'json' => ['linkedFamilyId' => $f2],
            'headers' => $authA + ['X-Family-Id' => $f1],
        ]);

        // B tente de créer le lien dans l'autre sens → 409 (paire canonique).
        $reverse = $clientB->request('POST', '/api/families/'.$f2.'/links', [
            'json' => ['linkedFamilyId' => $f1],
            'headers' => $authB + ['X-Family-Id' => $f2],
        ]);
        self::assertSame(409, $reverse->getStatusCode());
        self::assertSame('/errors/link-already-exists', $reverse->toArray(false)['type']);
    }

    public function testLinkToUnknownFamilyIsNotEnumerable(): void
    {
        [$client, $auth, $f1] = $this->adminWithFamily('unknownlink@example.com', 'Famille X');

        $response = $client->request('POST', '/api/families/'.$f1.'/links', [
            'json' => ['linkedFamilyId' => Uuid::v4()->toRfc4122()],
            'headers' => $auth + ['X-Family-Id' => $f1],
        ]);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('/errors/link-not-found', $response->toArray(false)['type']);
    }
}
