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

    private function addChild(string $familyId, string $email): string
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        $u = (new User())->setEmail($email);
        $u->setPasswordHash($hasher->hashPassword($u, self::PASSWORD));
        $m = (new Membership())->setUser($u)->setFamily($family)->setRole(MembershipRole::Child)->setStatus(MembershipStatus::Active);
        $this->em()->persist($u);
        $this->em()->persist($m);
        $this->em()->flush();

        return (string) $m->getId();
    }

    public function testRejectsFutureAnchorAndNonParentExceptionTarget(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust-guard@example.com');

        // P2-A : une date d'ancrage future est refusée (422) — sinon jours obsolètes non régénérés.
        $future = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => ['kind' => 'alternating', 'anchorDate' => '2026-12-01', 'handoverWeekday' => 0, 'handoverTime' => '19:00', 'parentAMembershipId' => $parentA, 'parentBMembershipId' => $parentB],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $future->getStatusCode(), 'ancre future refusée');

        // P2-E : un membership enfant ne peut pas être titulaire d'une exception (422).
        $childId = $this->addChild($familyId, 'child-'.uniqid().'@example.com');
        $exc = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $childId],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(422, $exc->getStatusCode(), 'titulaire d\'exception non-parent refusé');
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

    /**
     * Ré-échanger la MÊME date remplace l'exception au lieu de l'empiler.
     *
     * Deux exceptions contradictoires sur un même jour laissaient l'ordre d'application du moteur
     * — non l'utilisateur — décider chez qui l'enfant dort, et l'écran Réglages en listait deux.
     */
    public function testSecondExceptionOnSameRangeReplacesTheFirst(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust-swap@example.com');
        $this->putPattern($client, $h, $familyId, $parentA, $parentB);
        $this->runGeneration($familyId);

        $first = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentB, 'reason' => 'Échange'],
            'headers' => $h,
        ]);
        self::assertSame(201, $first->getStatusCode());
        $firstId = $first->toArray()['id'];

        // Même plage, même parent : rien de neuf — l'existante est rendue telle quelle.
        $replay = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentB, 'reason' => 'Échange'],
            'headers' => $h,
        ]);
        self::assertSame($firstId, $replay->toArray()['id'], 'un rejeu à l\'identique ne crée pas de jumelle');

        // Même plage, même parent, motif corrigé : l'existante est mise à jour, pas doublée.
        $amended = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentB, 'reason' => 'Stage de foot'],
            'headers' => $h,
        ]);
        self::assertSame($firstId, $amended->toArray()['id']);
        self::assertSame('Stage de foot', $amended->toArray()['reason']);

        // Ré-échange (retour chez A) : l'ancienne exception disparaît, une seule subsiste.
        $second = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentA, 'reason' => 'Échange'],
            'headers' => $h,
        ]);
        self::assertSame(201, $second->getStatusCode());
        self::assertNotSame($firstId, $second->toArray()['id']);

        $list = $client->request('GET', '/api/families/'.$familyId.'/custody/exceptions', ['headers' => $h])->toArray();
        $onJuly15 = array_values(array_filter($list, static fn ($e) => '2026-07-15' === $e['startDate']));
        self::assertCount(1, $onJuly15, 'une seule exception sur la date ré-échangée');
        self::assertSame($parentA, $onJuly15[0]['parentMembershipId']);

        $july15 = array_values(array_filter(
            $client->request('GET', '/api/families/'.$familyId.'/custody/days?from=2026-07-15&to=2026-07-15', ['headers' => $h])->toArray(),
            static fn ($d) => '2026-07-15' === $d['date'],
        ))[0];
        self::assertSame($parentA, $july15['parentMembershipId']);

        // Une exception d'une AUTRE plage n'est pas touchée par le remplacement.
        $other = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-20', 'endDate' => '2026-07-22', 'parentMembershipId' => $parentB],
            'headers' => $h,
        ]);
        self::assertSame(201, $other->getStatusCode());
        $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentB],
            'headers' => $h,
        ]);
        $after = $client->request('GET', '/api/families/'.$familyId.'/custody/exceptions', ['headers' => $h])->toArray();
        self::assertCount(2, $after, 'seule l\'exception de plage identique est remplacée');
    }

    /**
     * Échange à l'heure près (cdc §3.4) — les deux exemples de la demande du 2026-09-11, joués de
     * bout en bout : les jours matérialisés doivent porter le découpage annoncé, et la journée
     * revenir au parent **majoritaire**.
     */
    public function testHourlySwapSlicesTheMaterialisedDays(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust-hourly@example.com');
        // Rythme personnalisé : A le 25/09, B les 26, 27 et 28, A le 29 — la séquence de l'exemple.
        // L'ancre tombe un dimanche, le motif est donc lu à partir de ce jour-là.
        $put = $client->request('PUT', '/api/families/'.$familyId.'/custody/pattern', [
            'json' => [
                'kind' => 'custom',
                'anchorDate' => '2026-06-21',
                'handoverWeekday' => 0,
                'handoverTime' => '18:00',
                'dayStartTime' => '08:00',
                'parentAMembershipId' => $parentA,
                'parentBMembershipId' => $parentB,
                // 96 jours séparent l'ancre du 25/09, soit l'index 12 du cycle de 14 : le motif est
                // écrit pour que 25 → A, 26/27/28 → B et 29 → A (indices 12, 13, 0, 1, 2).
                'customPattern' => ['B', 'B', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'B'],
            ],
            'headers' => $h + ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(200, $put->getStatusCode());
        $this->runGeneration($familyId);

        $daysOf = static function (string $from, string $to) use ($client, $h, $familyId): array {
            $out = [];
            foreach ($client->request('GET', '/api/families/'.$familyId.'/custody/days?from='.$from.'&to='.$to, ['headers' => $h])->toArray() as $day) {
                $out[$day['date']] = $day;
            }

            return $out;
        };

        // Le dernier jour d'un bloc porte la transmission : il est déjà partagé avant tout échange.
        $before = $daysOf('2026-09-25', '2026-09-29');
        self::assertSame(
            [['from' => '00:00', 'to' => '18:00'], ['from' => '18:00', 'to' => '24:00']],
            array_map(static fn (array $s) => ['from' => $s['from'], 'to' => $s['to']], $before['2026-09-25']['segments']),
            'le 25 est le dernier jour de A : il se partage à 18:00',
        );
        self::assertCount(1, $before['2026-09-26']['segments'], 'un jour en plein bloc reste uni');

        // Exemple 1 — « le 26 pour A » : du 25 à 18:00 au 26 à 18:00.
        $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => [
                'startDate' => '2026-09-25', 'startTime' => '18:00',
                'endDate' => '2026-09-26', 'endTime' => '18:00',
                'parentMembershipId' => $parentA, 'reason' => 'Échange',
            ],
            'headers' => $h,
        ]);

        $after = $daysOf('2026-09-25', '2026-09-29');
        // Le 25 redevient uni : A ne rend plus les enfants ce soir-là.
        self::assertCount(1, $after['2026-09-25']['segments']);
        self::assertSame($parentA, $after['2026-09-25']['parentMembershipId']);
        // Le 26 passe à A jusqu'à 18:00, puis revient à B.
        self::assertSame(
            [
                ['from' => '00:00', 'to' => '18:00', 'parent' => $parentA],
                ['from' => '18:00', 'to' => '24:00', 'parent' => $parentB],
            ],
            array_map(static fn (array $s) => ['from' => $s['from'], 'to' => $s['to'], 'parent' => $s['parent']], $after['2026-09-26']['segments']),
        );
        // 18 h sur 24 chez A : la journée lui est comptée.
        self::assertSame($parentA, $after['2026-09-26']['parentMembershipId']);
        self::assertSame('exception', $after['2026-09-26']['source']);

        // Exemple 2 — « le 28 pour A », sans veille à prolonger : du 28 à 08:00 au 28 à 18:00.
        $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => [
                'startDate' => '2026-09-28', 'startTime' => '08:00',
                'endDate' => '2026-09-28', 'endTime' => '18:00',
                'parentMembershipId' => $parentA, 'reason' => 'Échange',
            ],
            'headers' => $h,
        ]);

        $final = $daysOf('2026-09-25', '2026-09-29');
        // « B jusqu'à 8h, A ensuite » : la tranche d'échange fusionne avec le rythme qui suit.
        self::assertSame(
            [
                ['from' => '00:00', 'to' => '08:00', 'parent' => $parentB],
                ['from' => '08:00', 'to' => '24:00', 'parent' => $parentA],
            ],
            array_map(static fn (array $s) => ['from' => $s['from'], 'to' => $s['to'], 'parent' => $s['parent']], $final['2026-09-28']['segments']),
        );
        self::assertSame($parentA, $final['2026-09-28']['parentMembershipId']);
        // Le 29 reste entièrement chez A, comme avant l'échange.
        self::assertCount(1, $final['2026-09-29']['segments']);
        self::assertSame($parentA, $final['2026-09-29']['parentMembershipId']);
    }

    /** Heure illisible ou créneau vide : refus 422 plutôt qu'un échange sans effet. */
    public function testHourlySwapRejectsUnreadableOrEmptyWindow(): void
    {
        static::mockTime(new \DateTimeImmutable('2026-06-23 10:00', new \DateTimeZone('Europe/Paris')));
        [$client, $h, $familyId, $parentA, $parentB] = $this->separatedFamilyWithTwoParents('cust-hourly2@example.com');
        $this->putPattern($client, $h, $familyId, $parentA, $parentB);

        $bad = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => ['startDate' => '2026-07-15', 'startTime' => '25:61', 'endDate' => '2026-07-15', 'parentMembershipId' => $parentA],
            'headers' => $h,
        ]);
        self::assertSame(422, $bad->getStatusCode());

        $empty = $client->request('POST', '/api/families/'.$familyId.'/custody/exceptions', [
            'json' => [
                'startDate' => '2026-07-15', 'startTime' => '18:00',
                'endDate' => '2026-07-15', 'endTime' => '08:00',
                'parentMembershipId' => $parentA,
            ],
            'headers' => $h,
        ]);
        self::assertSame(422, $empty->getStatusCode(), 'un créneau qui finit avant de commencer est refusé');
    }

    /** @param array<string, string> $h */
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
