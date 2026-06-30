<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Repository\FamilyRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 1 — gestion des membres (US-MBR-2/3/5) : modification rôle/admin/droits, couleur (self),
 * autorisations (403), protection du dernier admin (409), promotion parent (droits forcés).
 */
final class MemberManagementLot1Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} client, bearer admin, family id */
    private function adminWithFamily(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Membres', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    /** Ajoute un membre actif (rôle donné) directement en base et retourne son membership id. */
    private function addMember(string $familyId, string $email, MembershipRole $role, bool $isAdmin = false): string
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(FamilyRepository::class, $families);

        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole($role)->setIsAdmin($isAdmin)->setStatus(MembershipStatus::Active);

        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();

        return (string) $membership->getId();
    }

    public function testAdminUpdatesStepParentRights(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('mm-admin@example.com');
        $sp = $this->addMember($familyId, 'mm-sp@example.com', MembershipRole::StepParent);

        $patch = $client->request('PATCH', '/api/memberships/'.$sp, [
            'json' => ['rights' => ['agenda' => 'edit', 'children_notes' => 'view', 'custody' => 'none']],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $patch->getStatusCode());
        $rights = $patch->toArray()['rights'];
        self::assertSame('edit', $rights['agenda']);
        self::assertSame('view', $rights['children_notes']);
        self::assertSame('none', $rights['custody']);
    }

    public function testPromoteStepParentToParentForcesEdit(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('mm-admin2@example.com');
        $sp = $this->addMember($familyId, 'mm-sp2@example.com', MembershipRole::StepParent);

        $patch = $client->request('PATCH', '/api/memberships/'.$sp, [
            'json' => ['role' => 'parent'],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $patch->getStatusCode());
        self::assertSame('parent', $patch->toArray()['role']);
        self::assertSame('edit', $patch->toArray()['rights']['agenda']);
    }

    public function testAdminFlagOnNonParentRejected(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('mm-admin3@example.com');
        $sp = $this->addMember($familyId, 'mm-sp3@example.com', MembershipRole::StepParent);

        $patch = $client->request('PATCH', '/api/memberships/'.$sp, [
            'json' => ['isAdmin' => true],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(422, $patch->getStatusCode());
        self::assertSame('/errors/admin-requires-parent-role', $patch->toArray(false)['type']);
    }

    public function testDemoteAdminAmongTwoSucceedsButLastIsProtected(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('mm-admin4@example.com');
        $admin2 = $this->addMember($familyId, 'mm-admin4b@example.com', MembershipRole::Parent, isAdmin: true);

        // Deux admins : rétrograder le second est permis.
        $demote = $client->request('PATCH', '/api/memberships/'.$admin2, [
            'json' => ['isAdmin' => false],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $demote->getStatusCode());
        self::assertFalse($demote->toArray()['isAdmin']);

        // Il ne reste qu'un admin : on ne peut plus le rétrograder.
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        $selfId = array_values(array_filter($members, static fn ($m) => $m['self']))[0]['id'];

        $demoteLast = $client->request('PATCH', '/api/memberships/'.$selfId, [
            'json' => ['isAdmin' => false],
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(409, $demoteLast->getStatusCode());
        self::assertSame('/errors/last-admin-protected', $demoteLast->toArray(false)['type']);
    }

    public function testMemberCanSetOwnColorButNotOthersStructure(): void
    {
        [$adminClient, $adminAuth, $familyId] = $this->adminWithFamily('mm-admin5@example.com');
        $this->addMember($familyId, 'mm-sp5@example.com', MembershipRole::StepParent);

        // Le beau-parent se connecte.
        $spClient = self::createClient();
        $spClient->disableReboot();
        $spToken = $this->login($spClient, 'mm-sp5@example.com');
        $spAuth = $this->bearer($spToken) + ['X-Family-Id' => $familyId];

        // Trouve son propre membership.
        $members = $spClient->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $spAuth])->toArray();
        $selfId = array_values(array_filter($members, static fn ($m) => $m['self']))[0]['id'];
        $adminMember = array_values(array_filter($members, static fn ($m) => !$m['self']))[0];

        // Sa propre couleur : autorisé.
        $color = $spClient->request('PATCH', '/api/memberships/'.$selfId, [
            'json' => ['displayColor' => '#2E7D71'],
            'headers' => $spAuth + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $color->getStatusCode());
        self::assertSame('#2E7D71', $color->toArray()['displayColor']);

        // Modifier la structure d'un autre membre : refusé (family_settings:edit requis).
        $forbidden = $spClient->request('PATCH', '/api/memberships/'.$adminMember['id'], [
            'json' => ['rights' => ['agenda' => 'edit']],
            'headers' => $spAuth + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(403, $forbidden->getStatusCode());
    }

    public function testAdminRemovesMember(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('mm-admin6@example.com');
        $sp = $this->addMember($familyId, 'mm-sp6@example.com', MembershipRole::StepParent);

        $delete = $client->request('DELETE', '/api/memberships/'.$sp, [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(204, $delete->getStatusCode());

        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        self::assertCount(1, $members);
    }
}
