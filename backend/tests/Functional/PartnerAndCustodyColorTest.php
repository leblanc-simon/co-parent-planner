<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\CustodyPalette;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use App\Repository\FamilyRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Évolutions E1 et E4 de `specs/evolutions-2026-09.md`.
 *
 * **E1** — association parent ↔ beau-parent : relation **1 ↔ 1** portée par le beau-parent, déclarée
 * par un admin, rendue **des deux côtés**, et qui n'accorde aucun droit.
 * **E4** — couleur du planning **par spectateur** (cdc §8.2) : mon choix ne change rien chez l'autre.
 */
final class PartnerAndCustodyColorTest extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} */
    private function adminWithFamily(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Couple', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    /**
     * Membership de l'admin (créateur de la famille).
     *
     * @param array<string, string> $auth
     */
    private function adminMembership(Client $client, array $auth, string $familyId): string
    {
        foreach ($client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray() as $member) {
            if ($member['self']) {
                return $member['id'];
            }
        }
        self::fail('Membership de l\'admin introuvable.');
    }

    private function addMember(string $familyId, string $email, MembershipRole $role, ?RightLevel $custody = null): string
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(FamilyRepository::class, $families);

        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        $user = (new User())->setEmail($email)->setFullName('Membre '.$email)
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole($role)->setStatus(MembershipStatus::Active);
        if (null !== $custody) {
            $membership->addRight(new Right(RightDomain::Custody, $custody));
        }
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();

        return (string) $membership->getId();
    }

    /**
     * @param array<string, string> $auth
     * @param array<string, mixed>  $body
     */
    private function patch(Client $client, array $auth, string $familyId, string $membershipId, array $body): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('PATCH', '/api/memberships/'.$membershipId, [
            'json' => $body,
            'headers' => $auth + ['X-Family-Id' => $familyId, 'Content-Type' => 'application/merge-patch+json'],
        ]);
    }

    /** @param array<string, string> $auth */
    private function putColor(Client $client, array $auth, string $familyId, string $targetId, string $palette): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('PUT', '/api/families/'.$familyId.'/custody-colors', [
            'json' => ['targetMembershipId' => $targetId, 'palette' => $palette],
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
    }

    // ───────────────────────── E1 — association parent ↔ beau-parent ─────────────────────────

    public function testAdminAssociatesStepParentWithParent(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-admin@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-step@example.com', MembershipRole::StepParent);

        $res = $this->patch($client, $auth, $familyId, $step, ['partnerMembershipId' => $admin]);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame($admin, $res->toArray()['partnerMembershipId']);
        self::assertNotNull($res->toArray()['partnerName']);
    }

    /** La relation n'est stockée qu'une fois, mais les deux lignes la portent à la lecture. */
    public function testPartnerIsRenderedOnBothSides(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-both@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-both-step@example.com', MembershipRole::StepParent);
        $this->patch($client, $auth, $familyId, $step, ['partnerMembershipId' => $admin]);

        $byId = [];
        foreach ($client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray() as $member) {
            $byId[$member['id']] = $member;
        }
        self::assertSame($admin, $byId[$step]['partnerMembershipId']);
        self::assertSame($step, $byId[$admin]['partnerMembershipId']);
    }

    /** L'écriture accepte les deux sens : désigner depuis la ligne du parent marche aussi. */
    public function testAssociationCanBeDeclaredFromTheParentSide(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-side@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-side-step@example.com', MembershipRole::StepParent);

        $res = $this->patch($client, $auth, $familyId, $admin, ['partnerMembershipId' => $step]);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame($step, $res->toArray()['partnerMembershipId']);
    }

    public function testNullDetachesTheAssociation(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-detach@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-detach-step@example.com', MembershipRole::StepParent);
        $this->patch($client, $auth, $familyId, $step, ['partnerMembershipId' => $admin]);

        $res = $this->patch($client, $auth, $familyId, $step, ['partnerMembershipId' => null]);
        self::assertSame(200, $res->getStatusCode());
        self::assertNull($res->toArray()['partnerMembershipId']);
    }

    /** Deux parents sont les deux coparents, pas un couple. */
    public function testTwoParentsCannotBeAssociated(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-parents@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $other = $this->addMember($familyId, 'couple-parent-b@example.com', MembershipRole::Parent);

        $res = $this->patch($client, $auth, $familyId, $admin, ['partnerMembershipId' => $other]);
        self::assertSame(422, $res->getStatusCode());
    }

    /** 1 ↔ 1 : un parent déjà pris ne peut pas l'être une seconde fois. */
    public function testAParentCannotHaveTwoPartners(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-two@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $first = $this->addMember($familyId, 'couple-two-1@example.com', MembershipRole::StepParent);
        $second = $this->addMember($familyId, 'couple-two-2@example.com', MembershipRole::StepParent);
        $this->patch($client, $auth, $familyId, $first, ['partnerMembershipId' => $admin]);

        $res = $this->patch($client, $auth, $familyId, $second, ['partnerMembershipId' => $admin]);
        self::assertSame(422, $res->getStatusCode());
    }

    /** L'association est une donnée de structure : un non-admin ne la déclare pas. */
    public function testNonAdminCannotAssociate(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-rights@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-rights-step@example.com', MembershipRole::StepParent);

        $res = $this->patch($client, $this->bearer($this->login($client, 'couple-rights-step@example.com')), $familyId, $step, ['partnerMembershipId' => $admin]);
        self::assertSame(403, $res->getStatusCode());
    }

    /** Un rôle qui change emporte une association devenue absurde. */
    public function testPromotingTheStepParentDropsTheAssociation(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('couple-promote@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $step = $this->addMember($familyId, 'couple-promote-step@example.com', MembershipRole::StepParent);
        $this->patch($client, $auth, $familyId, $step, ['partnerMembershipId' => $admin]);

        $res = $this->patch($client, $auth, $familyId, $step, ['role' => 'parent']);
        self::assertSame(200, $res->getStatusCode());
        self::assertNull($res->toArray()['partnerMembershipId']);
    }

    // ───────────────────────── E4 — couleurs du planning ─────────────────────────

    public function testViewerChoosesAColorWithoutTouchingTheOtherParent(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('color-admin@example.com');
        $other = $this->addMember($familyId, 'color-parent-b@example.com', MembershipRole::Parent);

        $res = $this->putColor($client, $auth, $familyId, $other, CustodyPalette::Blue->value);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('blue', $res->toArray()['palette']);
        // Le triplet accompagne la clé : le client n'a pas à recopier la table du §8.2.
        self::assertSame('#5E7CB8', $res->toArray()['border']);

        $mine = $client->request('GET', '/api/families/'.$familyId.'/custody-colors', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        self::assertCount(1, $mine);

        // L'autre parent, lui, n'a rien vu changer : la préférence est locale au spectateur.
        $theirs = $client->request('GET', '/api/families/'.$familyId.'/custody-colors', [
            'headers' => $this->bearer($this->login($client, 'color-parent-b@example.com')) + ['X-Family-Id' => $familyId],
        ])->toArray();
        self::assertSame([], $theirs);
    }

    public function testChoosingTwiceUpdatesInPlace(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('color-twice@example.com');
        $other = $this->addMember($familyId, 'color-twice-b@example.com', MembershipRole::Parent);

        $this->putColor($client, $auth, $familyId, $other, CustodyPalette::Blue->value);
        $res = $this->putColor($client, $auth, $familyId, $other, CustodyPalette::Amber->value);
        self::assertSame(200, $res->getStatusCode());

        $colors = $client->request('GET', '/api/families/'.$familyId.'/custody-colors', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        self::assertCount(1, $colors);
        self::assertSame('amber', $colors[0]['palette']);
    }

    /** Les deux slots doivent rester distincts (§8.2). */
    public function testTheSameColorTwiceIsRefused(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('color-conflict@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $other = $this->addMember($familyId, 'color-conflict-b@example.com', MembershipRole::Parent);

        $this->putColor($client, $auth, $familyId, $other, CustodyPalette::Blue->value);
        $res = $this->putColor($client, $auth, $familyId, $admin, CustodyPalette::Blue->value);
        self::assertSame(422, $res->getStatusCode());
    }

    /** Palette fermée : une teinte hors catalogue est refusée, jamais ignorée en silence. */
    public function testAColorOutsideThePaletteIsRefused(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('color-free@example.com');
        $other = $this->addMember($familyId, 'color-free-b@example.com', MembershipRole::Parent);

        $res = $this->putColor($client, $auth, $familyId, $other, '#FF00FF');
        self::assertSame(422, $res->getStatusCode());
    }

    /**
     * Choisir ses couleurs est une préférence personnelle : elle est offerte à quiconque **voit** le
     * planning, un beau-parent en lecture seule compris — contrairement aux autres écritures de la
     * garde, qui exigent `custody:edit`.
     */
    public function testAReadOnlyMemberMayChooseTheirColors(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('color-ro@example.com');
        $admin = $this->adminMembership($client, $auth, $familyId);
        $this->addMember($familyId, 'color-ro-step@example.com', MembershipRole::StepParent, RightLevel::View);

        $res = $this->putColor($client, $this->bearer($this->login($client, 'color-ro-step@example.com')), $familyId, $admin, CustodyPalette::Green->value);
        self::assertSame(200, $res->getStatusCode());
    }
}
