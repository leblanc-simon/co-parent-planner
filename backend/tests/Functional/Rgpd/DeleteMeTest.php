<?php

declare(strict_types=1);

namespace App\Tests\Functional\Rgpd;

use App\Entity\Family;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Security\Encryption\BlindIndexHasher;
use App\Tests\Functional\ApiAuthTestCase;
use Symfony\Component\Clock\Clock;

/**
 * US-SET-4b — effacement du compte : anonymisation préservant l'intégrité, refus du dernier admin.
 */
final class DeleteMeTest extends ApiAuthTestCase
{
    public function testDeleteRefusedWhenLastAdmin(): void
    {
        [$client, $token] = $this->authenticatedClient('soleadmin@example.com');
        $user = $this->userByEmail('soleadmin@example.com');
        $family = $this->createFamily('Fam Solo');
        $this->addMembership($user, $family, admin: true);
        $this->em()->flush();

        $response = $client->request('DELETE', '/api/me', ['headers' => $this->bearer($token)]);
        self::assertSame(409, $response->getStatusCode());

        // Le compte n'a PAS été anonymisé (l'invariant dernier admin prime, même sur le RGPD).
        $this->em()->clear();
        $reloaded = $this->userByEmail('soleadmin@example.com');
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame(UserStatus::Active, $reloaded->getStatus());
    }

    public function testDeleteAnonymizesAccountAndRemovesMemberships(): void
    {
        [$client, $token] = $this->authenticatedClient('leaver@example.com');
        $user = $this->userByEmail('leaver@example.com');
        $user->setFullName('Marie Curie')->setPhone('+33600000000');
        $family = $this->createFamily('Fam Duo');
        $coadmin = $this->createActiveUser('coadmin@example.com');
        $this->addMembership($user, $family, admin: true);
        $this->addMembership($coadmin, $family, admin: true);
        $this->em()->flush();
        $userId = (string) $user->getId();

        $response = $client->request('DELETE', '/api/me', ['headers' => $this->bearer($token)]);
        self::assertSame(204, $response->getStatusCode());

        $this->em()->clear();
        $reloaded = $this->em()->getRepository(User::class)->find($userId);
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame(UserStatus::Deleted, $reloaded->getStatus());
        self::assertNull($reloaded->getFullName());
        self::assertNull($reloaded->getPhone());
        self::assertStringStartsWith('anonymized-', $reloaded->getEmail());

        // Memberships retirés (les fils survivraient via SET NULL) ; le co-admin reste.
        self::assertCount(0, $this->em()->getRepository(Membership::class)->findBy(['user' => $reloaded]));
        self::assertCount(1, $this->em()->getRepository(Membership::class)->findBy(['family' => $family]));
    }

    public function testDeleteScrubsInvitationsAddressedToSubject(): void
    {
        [$client, $token] = $this->authenticatedClient('scrubbed@example.com');
        $blind = self::getContainer()->get(BlindIndexHasher::class);
        self::assertInstanceOf(BlindIndexHasher::class, $blind);
        $hash = $blind->hash('scrubbed@example.com');

        $family = $this->createFamily('Famille invitante');
        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail('scrubbed@example.com')
            ->setEmailHash($hash)
            ->setRole(MembershipRole::StepParent)
            ->setTokenHash('token-hash')
            ->setExpiresAt(Clock::get()->now()->add(new \DateInterval('P1D')))
            ->setStatus(InvitationStatus::Pending);
        $this->em()->persist($invitation);
        $this->em()->flush();
        self::assertCount(1, $this->em()->getRepository(Invitation::class)->findBy(['emailHash' => $hash]));

        // L'utilisateur (sans membership admin) peut s'effacer ; ses invitations chiffrées disparaissent.
        self::assertSame(204, $client->request('DELETE', '/api/me', ['headers' => $this->bearer($token)])->getStatusCode());

        $this->em()->clear();
        self::assertCount(0, $this->em()->getRepository(Invitation::class)->findBy(['emailHash' => $hash]));
    }

    private function userByEmail(string $email): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createFamily(string $name): Family
    {
        $family = (new Family())->setName($name)->setType(FamilyType::Separated);
        $this->em()->persist($family);

        return $family;
    }

    private function addMembership(User $user, Family $family, bool $admin): void
    {
        $membership = (new Membership())
            ->setUser($user)
            ->setFamily($family)
            ->setRole(MembershipRole::Parent)
            ->setIsAdmin($admin)
            ->setStatus(MembershipStatus::Active);
        $this->em()->persist($membership);
    }
}
