<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Event;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\FamilyLinkStatus;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Repository\FamilyLinkRepository;
use App\Repository\FamilyRepository;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 5 — overlay familles liées : fusion RO des événements de la famille liée dans l'agenda,
 * projection minimale (anti-fuite), 422 sur lien non actif, RO strict, préférences overlay.
 */
final class EventOverlayLot5Test extends ApiAuthTestCase
{
    use ClockSensitiveTrait;

    /**
     * Famille A (via API, avec un événement) liée `active` à une famille B seedée (avec un événement).
     *
     * @return array{0: Client, 1: array<string,string>, 2: string, 3: string, 4: string, 5: string}
     *                                                                                               client, headers A scopés, familyAId, familyBId, eventBId, linkId
     */
    private function linkedFamilies(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyAId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Foyer A', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        $hA = $auth + ['X-Family-Id' => $familyAId];

        $parentA = $client->request('GET', '/api/families/'.$familyAId.'/members', ['headers' => $hA])->toArray()[0]['id'];
        $client->request('POST', '/api/families/'.$familyAId.'/events', [
            'json' => [
                'title' => 'Tennis (foyer A)', 'category' => 'sport', 'responsibleMembershipId' => $parentA,
                'wholeFamily' => true, 'childIds' => [], 'startsAt' => '2026-06-15T17:30:00+02:00',
            ],
            'headers' => $hA,
        ]);

        // ---- Famille B seedée (autre foyer) + événement + lien actif A↔B ----
        $families = self::getContainer()->get(FamilyRepository::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(FamilyRepository::class, $families);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $familyA = $families->find(Uuid::fromString($familyAId));
        self::assertInstanceOf(Family::class, $familyA);

        $familyB = (new Family())->setName('Foyer B')->setType(FamilyType::Separated);
        $userB = (new User())->setEmail('b-'.$email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $userB->setPasswordHash($hasher->hashPassword($userB, self::PASSWORD));
        $membershipB = (new Membership())->setUser($userB)->setFamily($familyB)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $eventB = (new Event())->setFamily($familyB)->setTitle('Judo (foyer B)')->setCategory(EventCategory::Sport)
            ->setResponsibleMembership($membershipB)->setWholeFamily(true)
            ->setStartsAt(new \DateTimeImmutable('2026-06-16 15:00', new \DateTimeZone('UTC')))
            ->setLocation('Dojo secret')->setDescription('Note privée du foyer B');
        $link = (new FamilyLink($familyA, $familyB, null))->setStatus(FamilyLinkStatus::Active);

        foreach ([$familyB, $userB, $membershipB, $eventB, $link] as $entity) {
            $this->em()->persist($entity);
        }
        $this->em()->flush();

        return [$client, $hA, $familyAId, (string) $familyB->getId(), (string) $eventB->getId(), (string) $link->getId()];
    }

    private function window(): string
    {
        return 'from=2026-06-01&to=2026-06-30';
    }

    public function testOverlayMergesLinkedEventsInReadOnlyProjection(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, , $familyBId] = $this->linkedFamilies('ov-merge@example.com');

        $occ = $client->request('GET', '/api/families/'.$this->familyIdFrom($hA).'/events?'.$this->window().'&overlay='.$familyBId, ['headers' => $hA])->toArray();

        $own = array_values(array_filter($occ, static fn ($o) => ($o['source'] ?? null) !== 'linked'));
        $linked = array_values(array_filter($occ, static fn ($o) => ($o['source'] ?? null) === 'linked'));

        self::assertCount(1, $own, 'événement propre de A');
        self::assertSame('Tennis (foyer A)', $own[0]['title']);
        // Le discriminant est porté par les DEUX formes : un client généré depuis l'OpenAPI ne peut
        // les distinguer que si l'événement propre l'annonce lui aussi (et pas seulement par
        // l'absence de la clé, qu'un modèle à valeurs par défaut ne sait pas voir).
        self::assertSame('own', $own[0]['source']);
        self::assertTrue($own[0]['editable']);
        self::assertNull($own[0]['color']);
        self::assertNull($own[0]['linkedFamilyId']);
        self::assertCount(1, $linked, 'événement de la famille liée B, projeté');
        self::assertSame('Judo (foyer B)', $linked[0]['title']);
        self::assertSame('#9C6FB0', $linked[0]['color']);
        self::assertFalse($linked[0]['editable']);
        self::assertSame($familyBId, $linked[0]['linkedFamilyId']);

        // Anti-fuite : aucun champ hors projection minimale sur l'objet overlay (§3.6).
        foreach (['childIds', 'responsibleMembershipId', 'description', 'location', 'seriesId', 'id', 'recurrence'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $linked[0], "champ interdit exposé via overlay : {$forbidden}");
        }
    }

    public function testOverlayInvalidLinkReturns422(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, $familyAId] = $this->linkedFamilies('ov-invalid@example.com');

        // Famille non liée (uuid aléatoire).
        $r1 = $client->request('GET', '/api/families/'.$familyAId.'/events?'.$this->window().'&overlay='.Uuid::v7(), ['headers' => $hA]);
        self::assertSame(422, $r1->getStatusCode());
        self::assertSame('/errors/overlay-link-invalid', $r1->toArray(false)['type']);

        // Overlay sur soi-même → 422.
        $r2 = $client->request('GET', '/api/families/'.$familyAId.'/events?'.$this->window().'&overlay='.$familyAId, ['headers' => $hA]);
        self::assertSame(422, $r2->getStatusCode());
    }

    public function testOverlayPendingLinkReturns422(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, $familyAId, $familyBId, , $linkId] = $this->linkedFamilies('ov-pending@example.com');

        // Repasser le lien en pending → overlay indisponible.
        $links = self::getContainer()->get(FamilyLinkRepository::class);
        self::assertInstanceOf(FamilyLinkRepository::class, $links);
        $link = $links->find(Uuid::fromString($linkId));
        self::assertInstanceOf(FamilyLink::class, $link);
        $link->setStatus(FamilyLinkStatus::Pending);
        $this->em()->flush();

        $r = $client->request('GET', '/api/families/'.$familyAId.'/events?'.$this->window().'&overlay='.$familyBId, ['headers' => $hA]);
        self::assertSame(422, $r->getStatusCode());
    }

    public function testMutationOnLinkedEventIsRefused(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, , , $eventBId] = $this->linkedFamilies('ov-ro@example.com');

        // PATCH d'un événement de B avec X-Family-Id: A → refusé (hors scope A ; RO strict §US-LINK-4).
        $r = $client->request('PATCH', '/api/events/'.$eventBId, [
            'json' => ['title' => 'Piraté'],
            'headers' => $hA + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertContains($r->getStatusCode(), [403, 404], 'aucun chemin d\'écriture overlay');
    }

    public function testOverlayPreferenceUpsert(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, $familyAId, , , $linkId] = $this->linkedFamilies('ov-pref@example.com');

        $put = $client->request('PUT', '/api/families/'.$familyAId.'/overlay-preferences', [
            'json' => ['familyLinkId' => $linkId, 'view' => 'agenda', 'enabled' => true],
            'headers' => $hA + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(200, $put->getStatusCode());
        self::assertTrue($put->toArray()['enabled']);

        $list = $client->request('GET', '/api/families/'.$familyAId.'/overlay-preferences', ['headers' => $hA])->toArray();
        self::assertCount(1, $list);
        self::assertSame($linkId, $list[0]['familyLinkId']);
        self::assertTrue($list[0]['enabled']);

        // Upsert idempotent : désactiver met à jour la même préférence.
        $client->request('PUT', '/api/families/'.$familyAId.'/overlay-preferences', [
            'json' => ['familyLinkId' => $linkId, 'view' => 'agenda', 'enabled' => false],
            'headers' => $hA + ['Content-Type' => 'application/json'],
        ]);
        $list2 = $client->request('GET', '/api/families/'.$familyAId.'/overlay-preferences', ['headers' => $hA])->toArray();
        self::assertCount(1, $list2);
        self::assertFalse($list2[0]['enabled']);
    }

    public function testOverlayPreferenceRejectsInactiveLink(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $hA, $familyAId] = $this->linkedFamilies('ov-pref-bad@example.com');

        $put = $client->request('PUT', '/api/families/'.$familyAId.'/overlay-preferences', [
            'json' => ['familyLinkId' => (string) Uuid::v7(), 'view' => 'agenda', 'enabled' => true],
            'headers' => $hA + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $put->getStatusCode());
    }

    /** @param array<string, string> $h */
    private function familyIdFrom(array $h): string
    {
        return $h['X-Family-Id'];
    }
}
