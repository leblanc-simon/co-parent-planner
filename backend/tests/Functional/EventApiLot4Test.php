<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Child;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use App\Repository\FamilyRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 4 — endpoints agenda : création (ponctuel / série), liste des occurrences, filtre catégorie,
 * validations (422 RFC 7807), idempotence, autorisations (Voter agenda), scope X-Family-Id.
 */
final class EventApiLot4Test extends ApiAuthTestCase
{
    use ClockSensitiveTrait;

    private const string TZ = 'Europe/Paris';

    /** @return array{0: Client, 1: array<string,string>, 2: string, 3: string, 4: string} client, headers scopés, familyId, parentA, childId */
    private function family(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Agenda', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        $scoped = $auth + ['X-Family-Id' => $familyId];

        $members = $client->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $scoped])->toArray();
        $parentA = $members[0]['id'];

        $childId = $this->addChildEntity($familyId, 'Léa');

        return [$client, $scoped, $familyId, $parentA, $childId];
    }

    private function addChildEntity(string $familyId, string $firstName): string
    {
        $family = $this->findFamily($familyId);
        $child = (new Child())->setFamily($family)->setFirstName($firstName);
        $this->em()->persist($child);
        $this->em()->flush();

        return (string) $child->getId();
    }

    private function addMember(string $familyId, string $email, MembershipRole $role, ?RightLevel $agenda): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($this->findFamily($familyId))->setRole($role)->setStatus(MembershipStatus::Active);
        if (null !== $agenda) {
            $membership->addRight(new Right(RightDomain::Agenda, $agenda));
        }
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();
    }

    private function findFamily(string $familyId): Family
    {
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        return $family;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function pointPayload(string $parentA, string $childId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cours de tennis',
            'category' => 'sport',
            'responsibleMembershipId' => $parentA,
            'childIds' => [$childId],
            'wholeFamily' => false,
            'location' => 'TC Bellevue',
            'startsAt' => '2026-06-23T17:30:00+02:00',
            'endsAt' => '2026-06-23T18:30:00+02:00',
        ], $overrides);
    }

    public function testCreatePointEventAndList(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-create@example.com');

        $create = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($parentA, $childId),
            'headers' => $h,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $body = $create->toArray();
        self::assertNull($body['seriesId']);
        self::assertSame('sport', $body['category']);
        self::assertSame([$childId], $body['childIds']);
        self::assertFalse($body['isRecurring']);

        $list = $client->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-06-30', ['headers' => $h]);
        self::assertSame(200, $list->getStatusCode());
        $occurrences = $list->toArray();
        self::assertCount(1, $occurrences);
        self::assertSame('Cours de tennis', $occurrences[0]['title']);
        self::assertStringStartsWith('2026-06-23T17:30:00', $occurrences[0]['startsAt']);
    }

    public function testCreateWholeFamilyEvent(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA] = $this->family('ag-whole@example.com');

        $create = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($parentA, '', ['childIds' => [], 'wholeFamily' => true, 'title' => 'Vacances']),
            'headers' => $h,
        ]);
        self::assertSame(201, $create->getStatusCode());
        $body = $create->toArray();
        self::assertTrue($body['wholeFamily']);
        self::assertSame([], $body['childIds']);
    }

    public function testCreateSeriesExpandsOccurrences(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-series@example.com');

        $create = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($parentA, $childId, [
                'recurrence' => ['freq' => 'weekly', 'count' => 4],
            ]),
            'headers' => $h,
        ]);
        self::assertSame(201, $create->getStatusCode());
        self::assertNotNull($create->toArray()['seriesId']);

        $list = $client->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-08-01', ['headers' => $h]);
        self::assertSame(200, $list->getStatusCode());
        $occ = $list->toArray();
        self::assertCount(4, $occ, 'la série hebdomadaire produit 4 occurrences');
        self::assertSame(['2026-06-23', '2026-06-30', '2026-07-07', '2026-07-14'], array_map(static fn ($o) => substr($o['startsAt'], 0, 10), $occ));
        self::assertTrue($occ[0]['isRecurring']);
    }

    public function testCategoryFilter(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-filter@example.com');
        $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId, ['category' => 'sport']), 'headers' => $h]);
        $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId, ['category' => 'musique', 'title' => 'Piano', 'startsAt' => '2026-06-24T10:00:00+02:00', 'endsAt' => '2026-06-24T11:00:00+02:00']), 'headers' => $h]);

        $sport = $client->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-06-30&category=sport', ['headers' => $h])->toArray();
        self::assertCount(1, $sport);
        self::assertSame('sport', $sport[0]['category']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function testValidationErrors(array $overrides, string $expectedType): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-val-'.uniqid().'@example.com');

        $response = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($parentA, $childId, $overrides),
            'headers' => $h,
        ]);
        self::assertSame(422, $response->getStatusCode());
        if ('' !== $expectedType) {
            self::assertSame($expectedType, $response->toArray(false)['type']);
        }
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'catégorie invalide' => [['category' => 'vacances'], '/errors/validation'];
        yield 'ni enfant ni famille' => [['childIds' => [], 'wholeFamily' => false], '/errors/validation'];
        yield 'enfant ET famille' => [['wholeFamily' => true], '/errors/validation'];
        yield 'intervalle invalide' => [['endsAt' => '2026-06-23T16:00:00+02:00'], '/errors/validation'];
        yield 'récurrence bornes exclusives' => [['recurrence' => ['freq' => 'weekly', 'until' => '2026-12-31', 'count' => 4]], '/errors/validation'];
        yield 'récurrence non bornée' => [['recurrence' => ['freq' => 'weekly']], '/errors/validation'];
        yield 'fréquence invalide' => [['recurrence' => ['freq' => 'daily', 'count' => 4]], '/errors/validation'];
        yield 'titre vide' => [['title' => ''], ''];
        yield 'titre espaces uniquement' => [['title' => '   '], '/errors/validation'];
        yield 'startsAt sans offset' => [['startsAt' => '2026-06-23T17:30:00'], '/errors/validation'];
        yield 'startsAt relatif' => [['startsAt' => 'now'], '/errors/validation'];
        yield 'startsAt non ISO' => [['startsAt' => '2026-06-23 17:30'], '/errors/validation'];
    }

    public function testChildFromAnotherFamilyRejected(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA] = $this->family('ag-x1@example.com');
        [, , $otherFamilyId] = $this->family('ag-x2@example.com');
        $foreignChild = $this->addChildEntity($otherFamilyId, 'Étranger');

        $response = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($parentA, $foreignChild),
            'headers' => $h,
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('/errors/validation', $response->toArray(false)['type']);
    }

    public function testResponsibleChildRejected(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, , $childId] = $this->family('ag-resp@example.com');
        $this->addMember($familyId, 'ag-childmember@example.com', MembershipRole::Child, RightLevel::View);
        $childMemberId = $client->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $h])->toArray();
        $childMembership = array_values(array_filter($childMemberId, static fn ($m) => 'child' === $m['role']))[0]['id'];

        $response = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->pointPayload($childMembership, $childId, ['responsibleMembershipId' => $childMembership]),
            'headers' => $h,
        ]);
        self::assertSame(422, $response->getStatusCode());
    }

    public function testIdempotencyKeyPreventsDuplicate(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-idem@example.com');
        $key = ['Idempotency-Key' => (string) Uuid::v7()];

        $first = $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $h + $key]);
        $second = $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $h + $key]);
        self::assertSame(201, $first->getStatusCode());
        self::assertSame($first->toArray()['id'], $second->toArray()['id'], 'même clé → même événement (rejeu)');

        $list = $client->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-06-30', ['headers' => $h]);
        self::assertCount(1, $list->toArray(), 'aucun doublon');
    }

    public function testViewMemberCannotCreateButCanRead(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [, $h, $familyId, $parentA, $childId] = $this->family('ag-view@example.com');
        $this->addMember($familyId, 'ag-viewer@example.com', MembershipRole::StepParent, RightLevel::View);

        $viewer = self::createClient();
        $viewer->disableReboot();
        $vh = $this->bearer($this->login($viewer, 'ag-viewer@example.com')) + ['X-Family-Id' => $familyId];

        self::assertSame(200, $viewer->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-06-30', ['headers' => $vh])->getStatusCode());
        $post = $viewer->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $vh]);
        self::assertSame(403, $post->getStatusCode());
        self::assertSame('/errors/forbidden', $post->toArray(false)['type']);
    }

    public function testNoneMemberCannotRead(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [, , $familyId] = $this->family('ag-none@example.com');
        $this->addMember($familyId, 'ag-noneuser@example.com', MembershipRole::StepParent, null);

        $none = self::createClient();
        $none->disableReboot();
        $nh = $this->bearer($this->login($none, 'ag-noneuser@example.com')) + ['X-Family-Id' => $familyId];

        self::assertSame(403, $none->request('GET', '/api/families/'.$familyId.'/events?from=2026-06-01&to=2026-06-30', ['headers' => $nh])->getStatusCode());
    }

    /**
     * @param array<string, string> $h
     *
     * @return array{0: string, 1: string} masterId, seriesId
     */
    private function createSeries(Client $client, array $h, string $parentA, string $childId): array
    {
        $body = $client->request('POST', '/api/families/'.$this->familyIdFrom($h).'/events', [
            'json' => $this->pointPayload($parentA, $childId, ['recurrence' => ['freq' => 'weekly', 'count' => 4]]),
            'headers' => $h,
        ])->toArray();

        return [$body['id'], $body['seriesId']];
    }

    /** @param array<string, string> $h */
    private function familyIdFrom(array $h): string
    {
        return $h['X-Family-Id'];
    }

    /**
     * @param array<string, string> $h
     * @param array<string, mixed>  $body
     */
    private function patch(Client $client, array $h, string $url, array $body): int
    {
        return $client->request('PATCH', $url, [
            'json' => $body,
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ])->getStatusCode();
    }

    /**
     * @param array<string, string> $h
     *
     * @return array<int, array<string, mixed>>
     */
    private function listOccurrences(Client $client, array $h): array
    {
        return $client->request('GET', '/api/families/'.$this->familyIdFrom($h).'/events?from=2026-06-01&to=2026-08-01', ['headers' => $h])->toArray();
    }

    public function testOverrideSingleOccurrence(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-override@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        $status = $this->patch($client, $h, '/api/events/'.$masterId.'?scope=occurrence', [
            'recurrenceDate' => '2026-06-30',
            'startsAt' => '2026-06-30T19:00:00+02:00',
            'endsAt' => '2026-06-30T20:00:00+02:00',
            'title' => 'Tennis (reporté)',
        ]);
        self::assertSame(200, $status);

        $occ = $this->listOccurrences($client, $h);
        self::assertCount(4, $occ, 'toujours 4 occurrences (une remplacée)');
        $june30 = array_values(array_filter($occ, static fn ($o) => str_starts_with($o['startsAt'], '2026-06-30')))[0];
        self::assertStringStartsWith('2026-06-30T19:00:00', $june30['startsAt']);
        self::assertTrue($june30['isOverride']);
        self::assertSame('Tennis (reporté)', $june30['title']);
        // Les autres occurrences restent inchangées (17:30).
        $june23 = array_values(array_filter($occ, static fn ($o) => str_starts_with($o['startsAt'], '2026-06-23')))[0];
        self::assertStringStartsWith('2026-06-23T17:30:00', $june23['startsAt']);
    }

    public function testExcludeSingleOccurrence(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-exdate@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        $delete = $client->request('DELETE', '/api/events/'.$masterId.'?scope=occurrence&recurrenceDate=2026-07-07', ['headers' => $h]);
        self::assertSame(204, $delete->getStatusCode());

        $occ = $this->listOccurrences($client, $h);
        self::assertSame(['2026-06-23', '2026-06-30', '2026-07-14'], array_map(static fn ($o) => substr($o['startsAt'], 0, 10), $occ));

        // Idempotent : re-supprimer la même occurrence reste 204.
        self::assertSame(204, $client->request('DELETE', '/api/events/'.$masterId.'?scope=occurrence&recurrenceDate=2026-07-07', ['headers' => $h])->getStatusCode());
    }

    public function testEditSeriesInPlace(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-series-edit@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$masterId.'?scope=series', ['location' => 'Nouveau gymnase']));

        $occ = $this->listOccurrences($client, $h);
        self::assertCount(4, $occ);
        foreach ($occ as $o) {
            self::assertSame('Nouveau gymnase', $o['location']);
        }
    }

    public function testEditSeriesThisAndFutureSplits(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-split@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        // À partir du 07/07 : nouveau lieu ; les occurrences antérieures sont préservées.
        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$masterId.'?scope=series', [
            'recurrenceDate' => '2026-07-07',
            'location' => 'Nouveau gymnase',
        ]));

        $occ = $this->listOccurrences($client, $h);
        self::assertCount(4, $occ, 'la scission conserve le total d\'occurrences');
        $byDate = [];
        foreach ($occ as $o) {
            $byDate[substr($o['startsAt'], 0, 10)] = $o['location'];
        }
        self::assertSame('TC Bellevue', $byDate['2026-06-23']);
        self::assertSame('TC Bellevue', $byDate['2026-06-30']);
        self::assertSame('Nouveau gymnase', $byDate['2026-07-07']);
        self::assertSame('Nouveau gymnase', $byDate['2026-07-14']);
    }

    public function testDeleteWholeSeries(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-delseries@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        self::assertSame(204, $client->request('DELETE', '/api/events/'.$masterId.'?scope=series', ['headers' => $h])->getStatusCode());
        self::assertCount(0, $this->listOccurrences($client, $h));
    }

    public function testOrphanOverrideHiddenAfterFreqChange(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-orphan@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId); // weekly count 4 depuis 23/06

        // Override de l'occurrence du 30/06.
        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$masterId.'?scope=occurrence', [
            'recurrenceDate' => '2026-06-30', 'startsAt' => '2026-06-30T19:00:00+02:00', 'endsAt' => '2026-06-30T20:00:00+02:00',
        ]));

        // Passage en mensuel (ancre 23/06) : le 30/06 n'est plus une occurrence → override orphelin.
        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$masterId.'?scope=series', [
            'recurrence' => ['freq' => 'monthly', 'count' => 4],
        ]));

        $dates = array_map(static fn ($o) => substr($o['startsAt'], 0, 10), $this->listOccurrences($client, $h));
        self::assertNotContains('2026-06-30', $dates, 'override orphelin ignoré au rendu (§4.11)');
        self::assertContains('2026-06-23', $dates, 'occurrence mensuelle du 23/06 présente');
    }

    public function testSplitToNonRecurringDissolvesTail(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-split-none@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId); // weekly count 4

        // « À partir du 07/07, un seul événement » : scission avec freq=none.
        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$masterId.'?scope=series', [
            'recurrenceDate' => '2026-07-07', 'recurrence' => ['freq' => 'none'],
        ]));

        $occ = $this->listOccurrences($client, $h);
        self::assertSame(['2026-06-23', '2026-06-30', '2026-07-07'], array_map(static fn ($o) => substr($o['startsAt'], 0, 10), $occ));
        // Le 07/07 est devenu un événement ponctuel (plus de série).
        $tail = array_values(array_filter($occ, static fn ($o) => str_starts_with($o['startsAt'], '2026-07-07')))[0];
        self::assertNull($tail['seriesId']);
        self::assertFalse($tail['isRecurring']);
        // Il est éditable sans portée (événement ponctuel).
        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$tail['id'], ['title' => 'Unique']));
    }

    public function testScopeRequiredForSeriesMutation(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-scopereq@example.com');
        [$masterId] = $this->createSeries($client, $h, $parentA, $childId);

        $status = $this->patch($client, $h, '/api/events/'.$masterId, ['location' => 'X']);
        self::assertSame(422, $status);
    }

    public function testEditAndDeletePointEvent(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-point@example.com');
        $eventId = $client->request('POST', '/api/families/'.$this->familyIdFrom($h).'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        self::assertSame(200, $this->patch($client, $h, '/api/events/'.$eventId, ['title' => 'Tennis modifié']));
        $occ = $this->listOccurrences($client, $h);
        self::assertSame('Tennis modifié', $occ[0]['title']);

        self::assertSame(204, $client->request('DELETE', '/api/events/'.$eventId, ['headers' => $h])->getStatusCode());
        self::assertCount(0, $this->listOccurrences($client, $h));
    }

    public function testOptimisticConflict(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, , $parentA, $childId] = $this->family('ag-lock@example.com');
        $eventId = $client->request('POST', '/api/families/'.$this->familyIdFrom($h).'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        $status = $this->patch($client, $h, '/api/events/'.$eventId, ['title' => 'X', 'expectedVersion' => 99]);
        self::assertSame(409, $status);
    }

    public function testScopeHeaderRequiredAndForeignFamilyForbidden(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-01 10:00', new \DateTimeZone(self::TZ)));
        [$client, $h, $familyId, $parentA, $childId] = $this->family('ag-scope1@example.com');
        [, , $otherFamilyId] = $this->family('ag-scope2@example.com');

        // Header manquant → 400.
        $noHeader = $this->bearer($this->login($client, 'ag-scope1@example.com'));
        $missing = $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $this->pointPayload($parentA, $childId), 'headers' => $noHeader]);
        self::assertSame(400, $missing->getStatusCode());

        // Famille dont l'utilisateur n'est pas membre → 403.
        $foreign = $client->request('GET', '/api/families/'.$otherFamilyId.'/events?from=2026-06-01&to=2026-06-30', [
            'headers' => $this->bearer($this->login($client, 'ag-scope1@example.com')) + ['X-Family-Id' => $otherFamilyId],
        ]);
        self::assertSame(403, $foreign->getStatusCode());
    }
}
