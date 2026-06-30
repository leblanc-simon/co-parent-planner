<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Repository\FamilyRepository;
use App\Service\Custody\CustodyGenerator;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 3 — endpoints garde : pattern, days, exceptions, stats ; famille unie (404/409) ; droits.
 */
final class CustodyApiLot3Test extends ApiAuthTestCase
{
    use ClockSensitiveTrait;

    /** @return array{0: Client, 1: array<string,string>, 2: string, 3: string, 4: string} */
    private function separatedFamilyWithTwoParents(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Garde', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];

        // Membership du créateur (parent A).
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        $parentA = $members[0]['id'];

        // Parent B seedé directement.
        $parentB = $this->addParent($familyId, 'b-'.$email);

        return [$client, $auth + ['X-Family-Id' => $familyId], $familyId, $parentA, $parentB];
    }

    private function addParent(string $familyId, string $email): string
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        $u = (new User())->setEmail($email);
        $u->setPasswordHash($hasher->hashPassword($u, self::PASSWORD));
        $m = (new Membership())->setUser($u)->setFamily($family)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $this->em()->persist($u);
        $this->em()->persist($m);
        $this->em()->flush();

        return (string) $m->getId();
    }

    /** Simule le worker asynchrone (le PUT pattern dispatche RegenerateCustody en in-memory en test). */
    private function runGeneration(string $familyId): void
    {
        $gen = self::getContainer()->get(CustodyGenerator::class);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(CustodyGenerator::class, $gen);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);
        $gen->generate($family);
    }

    public function testPatternDaysStatsAndException(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust1@example.com');

        // PUT pattern (alternating).
        $put = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => $parentA, 'parentBMembershipId' => $parentB],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(200, $put->getStatusCode());
        self::assertSame('alternating', $put->toArray()['kind']);

        // GET pattern.
        self::assertSame(200, $client->request('GET', '/api/families/'.$familyId.'/custody/pattern', ['headers' => $h])->getStatusCode());

        // Simule le worker → matérialise les jours.
        $this->runGeneration($familyId);

        // GET days sur juillet.
        $days = $client->request('GET', '/api/families/'.$familyId.'/custody/days?from=2026-07-01&to=2026-07-31', ['headers' => $h]);
        self::assertSame(200, $days->getStatusCode());
        self::assertCount(31, $days->toArray());

        // GET stats.
        $stats = $client->request('GET', '/api/families/'.$familyId.'/custody/stats?from=2026-07-01&to=2026-07-31', ['headers' => $h]);
        self::assertSame(200, $stats->getStatusCode());
        self::assertSame(31, $stats->toArray()['total']);

        // POST exception (un jour chez B).
        $exc = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentB, 'reason' => 'imprévu'],
            'headers' => $h,
        ]);
        self::assertSame(201, $exc->getStatusCode());
        $excId = $exc->toArray()['id'];

        // Le jour est bien passé en exception (B).
        $july15 = array_values(array_filter(
            $client->request('GET', '/api/families/'.$familyId.'/custody/days?from=2026-07-15&to=2026-07-15', ['headers' => $h])->toArray(),
            static fn ($d) => '2026-07-15' === $d['date'],
        ))[0];
        self::assertSame('exception', $july15['source']);
        self::assertSame($parentB, $july15['parentMembershipId']);

        // DELETE exception → recalcul.
        self::assertSame(204, $client->request('DELETE', '/api/families/'.$familyId.'/custody/exceptions/'.$excId, ['headers' => $h])->getStatusCode());
    }

    private function putPattern(Client $client, array $h, string $familyId, string $pa, string $pb): void
    {
        $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => $pa, 'parentBMembershipId' => $pb],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
    }

    public function testManualHolidayAndRuleAppliesToDays(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $pa, $pb] = $this->separatedFamilyWithTwoParents('cust4@example.com');
        $this->putPattern($client, $h, $familyId, $pa, $pb);

        // Période manuelle.
        $holiday = $client->request('POST', '/api/families/'.$familyId.'/holidays', [
            'json' => ['label' => 'Pont', 'startDate' => '2026-07-06', 'endDate' => '2026-07-10'],
            'headers' => $h,
        ]);
        self::assertSame(201, $holiday->getStatusCode());
        $holidayId = $holiday->toArray()['id'];

        // Règle « tout chez B ».
        $rule = $client->request('PUT', '/api/holidays/'.$holidayId.'/rule', [
            'json' => ['kind' => 'fixed_parent', 'params' => ['parent' => 'B']],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(200, $rule->getStatusCode());
        self::assertSame('fixed_parent', $rule->toArray()['ruleKind']);

        $this->runGeneration($familyId);

        $day = array_values(array_filter(
            $client->request('GET', '/api/families/'.$familyId.'/custody/days?from=2026-07-08&to=2026-07-08', ['headers' => $h])->toArray(),
            static fn ($d) => '2026-07-08' === $d['date'],
        ))[0];
        self::assertSame('holiday', $day['source']);
        self::assertSame($pb, $day['parentMembershipId']);

        // Liste des périodes.
        $list = $client->request('GET', '/api/families/'.$familyId.'/holidays', ['headers' => $h]);
        self::assertSame(200, $list->getStatusCode());
        self::assertCount(1, $list->toArray());
    }

    public function testImportHolidaysAsyncViaFakeProvider(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId] = $this->separatedFamilyWithTwoParents('cust5@example.com');

        // Réglages requis pour l'import (zone + académie).
        $client->request('PATCH', '/api/families/'.$familyId, [
            'json' => ['schoolZone' => 'A', 'academy' => 'Bordeaux'],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Déclenche l'import (202, asynchrone).
        $import = $client->request('POST', '/api/families/'.$familyId.'/holidays/import', ['headers' => $h]);
        self::assertSame(202, $import->getStatusCode());

        // Simule le worker (le double renvoie 2 périodes).
        $handler = self::getContainer()->get(\App\MessageHandler\ImportSchoolHolidaysHandler::class);
        self::assertInstanceOf(\App\MessageHandler\ImportSchoolHolidaysHandler::class, $handler);
        $handler(new \App\Message\ImportSchoolHolidays($familyId));

        $list = $client->request('GET', '/api/families/'.$familyId.'/holidays', ['headers' => $h]);
        self::assertCount(2, $list->toArray());
        self::assertSame('official', $list->toArray()[0]['source']);
    }

    public function testValidationErrors(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust2@example.com');

        // handoverTime invalide.
        $r1 = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '25:00', 'parentAMembershipId' => $parentA, 'parentBMembershipId' => $parentB],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $r1->getStatusCode());

        // parents identiques.
        $r2 = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => $parentA, 'parentBMembershipId' => $parentA],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $r2->getStatusCode());

        // custom de mauvaise longueur.
        $r3 = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'custom', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => $parentA, 'parentBMembershipId' => $parentB, 'customPattern' => ['A', 'B']],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $r3->getStatusCode());
    }

    public function testIntactFamilyReadsAreNotFoundAndMutationsConflict(): void
    {
        [$client, $token] = $this->authenticatedClient('cust3@example.com');
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Unie', 'type' => 'intact'],
            'headers' => $auth,
        ])->toArray()['id'];
        $h = $auth + ['X-Family-Id' => $familyId];

        // Lecture → 404 (masquage fonctionnel).
        self::assertSame(404, $client->request('GET', '/api/families/'.$familyId.'/custody/pattern', ['headers' => $h])->getStatusCode());
        self::assertSame(404, $client->request('GET', '/api/families/'.$familyId.'/custody/days', ['headers' => $h])->getStatusCode());

        // Mutation → 409 (/errors/custody-not-applicable).
        $put = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-06-21', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => (string) Uuid::v4(), 'parentBMembershipId' => (string) Uuid::v4()],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(409, $put->getStatusCode());
        self::assertSame('/errors/custody-not-applicable', $put->toArray(false)['type']);
    }
}
