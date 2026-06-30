<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Security\Auth\TokenHasher;
use App\Security\Encryption\BlindIndexHasher;
use Symfony\Component\Clock\Clock;

/**
 * Lot 1 — invitations (US-INV-1/2/3) : création (admin), récap/liste, régénération, acceptation
 * (expirée → 410, déjà membre → 409, parent + admin proposé, droits enfant normalisés).
 */
final class InvitationLot1Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} */
    private function adminWithFamily(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Inv', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    public function testCreateAndListInvitation(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('inv-admin@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];

        $create = $client->request('POST', '/api/families/'.$familyId.'/invitations', [
            'json' => ['email' => 'beauparent@example.com', 'role' => 'step_parent', 'rights' => ['agenda' => 'edit', 'children_notes' => 'view']],
            'headers' => $scoped,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $body = $create->toArray();
        self::assertSame('beauparent@example.com', $body['email']);
        self::assertSame('step_parent', $body['role']);
        self::assertArrayNotHasKey('token', $body);
        self::assertArrayNotHasKey('tokenHash', $body);
        self::assertSame('edit', $body['proposedRights']['agenda']);

        $list = $client->request('GET', '/api/families/'.$familyId.'/invitations', ['headers' => $scoped]);
        self::assertSame(200, $list->getStatusCode());
        self::assertCount(1, $list->toArray());
    }

    public function testReinviteRegeneratesPending(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('inv-admin2@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];

        foreach (['view', 'edit'] as $level) {
            $client->request('POST', '/api/families/'.$familyId.'/invitations', [
                'json' => ['email' => 'again@example.com', 'role' => 'step_parent', 'rights' => ['agenda' => $level]],
                'headers' => $scoped,
            ]);
        }

        $list = $client->request('GET', '/api/families/'.$familyId.'/invitations', ['headers' => $scoped]);
        self::assertCount(1, $list->toArray(), 'Une seule invitation pending après régénération.');
    }

    public function testInviteExistingMemberConflicts(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('inv-admin3@example.com');

        $response = $client->request('POST', '/api/families/'.$familyId.'/invitations', [
            'json' => ['email' => 'inv-admin3@example.com', 'role' => 'step_parent'],
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('/errors/already-member', $response->toArray(false)['type']);
    }

    public function testInviteParentWithAdminRejectedForNonParent(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('inv-admin4@example.com');

        $response = $client->request('POST', '/api/families/'.$familyId.'/invitations', [
            'json' => ['email' => 'x@example.com', 'role' => 'step_parent', 'isAdmin' => true],
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('/errors/admin-requires-parent-role', $response->toArray(false)['type']);
    }

    public function testAcceptExpiredInvitationReturns410(): void
    {
        [$client, $token] = $this->authenticatedClient('expired-invitee@example.com');
        $this->seedInvitation('expired-invitee@example.com', 'tok-expired', MembershipRole::StepParent, expiresInDays: -1);

        $response = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'tok-expired'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(410, $response->getStatusCode());
        self::assertSame('/errors/invitation-expired', $response->toArray(false)['type']);
    }

    public function testAcceptParentInvitationGrantsAdmin(): void
    {
        [$client, $token] = $this->authenticatedClient('newparent@example.com');
        $this->seedInvitation('newparent@example.com', 'tok-parent', MembershipRole::Parent, proposedAdmin: true);

        $accept = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'tok-parent'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(200, $accept->getStatusCode());
        $family = $accept->toArray();
        self::assertSame('parent', $family['role']);
        self::assertTrue($family['isAdmin']);

        // L'acceptation journalise invitation.accepted ET membership.created (§3.3, action sensible).
        $actions = $this->em()->getConnection()->fetchFirstColumn(
            "SELECT action FROM audit_logs WHERE action IN ('invitation.accepted', 'membership.created')",
        );
        self::assertContains('invitation.accepted', $actions);
        self::assertContains('membership.created', $actions);
    }

    public function testAcceptChildInvitationNormalizesRights(): void
    {
        [$client, $token] = $this->authenticatedClient('teen@example.com');
        // Demande illégale pour un enfant : expenses + édition de notes → normalisée.
        $this->seedInvitation('teen@example.com', 'tok-child', MembershipRole::Child, rights: ['expenses' => 'view', 'children_notes' => 'edit', 'agenda' => 'view']);

        $accept = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'tok-child'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(200, $accept->getStatusCode());
        $familyId = $accept->toArray()['id'];

        // Vérifie les droits effectifs via la liste des membres.
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $this->bearer($token) + ['X-Family-Id' => $familyId],
        ])->toArray();
        $me = array_values(array_filter($members, static fn ($m) => $m['self']))[0];
        self::assertSame('none', $me['rights']['expenses'], 'expenses interdit à un enfant');
        self::assertSame('view', $me['rights']['children_notes'], "pas d'édition de notes pour un enfant");
        self::assertSame('view', $me['rights']['agenda']);
        self::assertSame('none', $me['rights']['family_settings']);
    }

    /**
     * @param array<string, string> $rights
     */
    private function seedInvitation(string $email, string $plainToken, MembershipRole $role, array $rights = [], bool $proposedAdmin = false, int $expiresInDays = 7): void
    {
        $hasher = self::getContainer()->get(TokenHasher::class);
        $blind = self::getContainer()->get(BlindIndexHasher::class);
        self::assertInstanceOf(TokenHasher::class, $hasher);
        self::assertInstanceOf(BlindIndexHasher::class, $blind);

        // Famille avec un admin fondateur (pour qu'une invitation parent ne soit pas le seul admin).
        $founder = (new User())->setEmail('founder-'.$email)->setStatus(\App\Enum\UserStatus::Active);
        $founder->setPasswordHash('x');
        $family = (new Family())->setName('Famille Hôte')->setType(\App\Enum\FamilyType::Separated);
        $founderMembership = (new Membership())->setUser($founder)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);

        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail($email)
            ->setEmailHash($blind->hash($email))
            ->setRole($role)
            ->setProposedRights($rights)
            ->setProposedAdmin($proposedAdmin)
            ->setTokenHash($hasher->hash($plainToken))
            ->setExpiresAt(Clock::get()->now()->modify(($expiresInDays >= 0 ? '+' : '-').abs($expiresInDays).' days'))
            ->setStatus(InvitationStatus::Pending);

        $em = $this->em();
        $em->persist($founder);
        $em->persist($family);
        $em->persist($founderMembership);
        $em->persist($invitation);
        $em->flush();
    }
}
