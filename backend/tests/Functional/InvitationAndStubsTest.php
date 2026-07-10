<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Family;
use App\Entity\Invitation;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Security\Auth\TokenHasher;

/**
 * US-ONB-1 (acceptation d'invitation). Les endpoints RGPD, jadis 501 (décision D7), sont
 * implémentés au Lot 8 et couverts par les tests dédiés (App\Tests\Functional\Rgpd\*).
 */
final class InvitationAndStubsTest extends ApiAuthTestCase
{
    /**
     * @param array<string, string> $rights
     */
    private function createInvitation(string $invitedEmail, string $plaintextToken, MembershipRole $role, array $rights = []): void
    {
        $hasher = self::getContainer()->get(TokenHasher::class);
        self::assertInstanceOf(TokenHasher::class, $hasher);
        $blind = self::getContainer()->get(\App\Security\Encryption\BlindIndexHasher::class);
        self::assertInstanceOf(\App\Security\Encryption\BlindIndexHasher::class, $blind);

        $family = (new Family())->setName('Famille invitante')->setType(FamilyType::Separated);
        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail($invitedEmail)
            ->setEmailHash($blind->hash($invitedEmail))
            ->setRole($role)
            ->setProposedRights($rights)
            ->setTokenHash($hasher->hash($plaintextToken))
            ->setExpiresAt(\Symfony\Component\Clock\Clock::get()->now()->add(new \DateInterval('P1D')))
            ->setStatus(InvitationStatus::Pending);

        $this->em()->persist($family);
        $this->em()->persist($invitation);
        $this->em()->flush();
    }

    public function testAcceptInvitationCreatesMembership(): void
    {
        [$client, $token] = $this->authenticatedClient('carol@example.com');
        $this->createInvitation('carol@example.com', 'inv-token-123', MembershipRole::StepParent, ['agenda' => 'edit']);

        $accept = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'inv-token-123'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(200, $accept->getStatusCode());
        $family = $accept->toArray();
        self::assertSame('Famille invitante', $family['name']);
        self::assertSame('step_parent', $family['role']);
        self::assertFalse($family['isAdmin']);

        // La famille rejointe apparaît désormais dans mes familles.
        $list = $client->request('GET', '/api/families', ['headers' => $this->bearer($token)]);
        self::assertCount(1, $list->toArray());
    }

    public function testAcceptInvitationForAnotherEmailIsForbidden(): void
    {
        [$client, $token] = $this->authenticatedClient('dan@example.com');
        $this->createInvitation('someone-else@example.com', 'tok-mismatch', MembershipRole::StepParent);

        $response = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'tok-mismatch'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(403, $response->getStatusCode());
    }

    public function testAcceptInvalidInvitationIsRejected(): void
    {
        [$client, $token] = $this->authenticatedClient('eve@example.com');

        $response = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'does-not-exist'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testAcceptRequiresAuthentication(): void
    {
        $client = self::createClient();
        $response = $client->request('POST', '/api/invitations/accept', [
            'json' => ['token' => 'whatever'],
            'headers' => ['Accept' => 'application/json'],
        ]);
        self::assertSame(401, $response->getStatusCode());
    }
}
