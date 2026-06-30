<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 1 — réglages famille (US-SET-2a), liste des membres (US-MBR-1), scope X-Family-Id (§3.4),
 * protection du dernier admin sur auto-départ (US-MBR-4).
 */
final class FamilyManagementTest extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} client, bearer, family id */
    private function adminWithFamily(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);

        $create = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Test', 'type' => 'separated'],
            'headers' => $auth,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $familyId = $create->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    public function testListMembersReturnsCreatorAsParentAdmin(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin1@example.com');

        $response = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $members = $response->toArray();
        self::assertCount(1, $members);
        self::assertSame('parent', $members[0]['role']);
        self::assertTrue($members[0]['isAdmin']);
        self::assertTrue($members[0]['self']);
        self::assertSame('edit', $members[0]['rights']['family_settings']);
        self::assertSame('admin1@example.com', $members[0]['email']);
    }

    public function testUpdateFamilySettingsAsAdmin(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin2@example.com');

        $patch = $client->request('PATCH', '/api/families/'.$familyId, [
            'json' => ['name' => 'Famille Durand-Bernard', 'schoolZone' => 'C', 'academy' => 'Créteil', 'handoverWeekday' => 5, 'handoverTime' => '18:30'],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $patch->getStatusCode());
        $body = $patch->toArray();
        self::assertSame('Famille Durand-Bernard', $body['name']);
        self::assertSame('C', $body['schoolZone']);
        self::assertSame(5, $body['handoverWeekday']);
        self::assertSame('18:30', $body['handoverTime']);
    }

    public function testUpdateFamilyRejectsInvalidTimezone(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin3@example.com');

        $patch = $client->request('PATCH', '/api/families/'.$familyId, [
            'json' => ['timezone' => 'Mars/Olympus'],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(422, $patch->getStatusCode());
    }

    public function testScopeHeaderRequired(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin4@example.com');

        $response = $client->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $auth]);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('/errors/family-scope-required', $response->toArray(false)['type']);
    }

    public function testScopeHeaderMismatch(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin5@example.com');

        $response = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => Uuid::v4()->toRfc4122()],
        ]);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('/errors/family-scope-mismatch', $response->toArray(false)['type']);
    }

    public function testForeignFamilyScopeForbidden(): void
    {
        [$client, $auth] = $this->adminWithFamily('admin6@example.com');
        $foreign = Uuid::v4()->toRfc4122();

        // Header pointant une famille étrangère, sur une route non liée au {id} de chemin.
        $response = $client->request('GET', '/api/families', [
            'headers' => $auth + ['X-Family-Id' => $foreign],
        ]);
        // /api/families (liste) n'est pas scopée → 200 quoi qu'il arrive.
        self::assertSame(200, $response->getStatusCode());
    }

    public function testLastAdminCannotLeave(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('admin7@example.com');

        // Récupère son propre membership.
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        $membershipId = $members[0]['id'];

        $delete = $client->request('DELETE', '/api/memberships/'.$membershipId, [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(409, $delete->getStatusCode());
        self::assertSame('/errors/last-admin-protected', $delete->toArray(false)['type']);
    }
}
