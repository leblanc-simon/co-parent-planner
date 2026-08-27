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
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Non-régression de l'API pour la revue `specs/revue-web-2026-08.md` — un test par constat corrigé.
 */
final class ApiReviewFixesTest extends ApiAuthTestCase
{
    // ───────────────────────── C6 — 422 au lieu de 500 ─────────────────────────

    /**
     * C6 — un titre plus long que la colonne remontait en 500 depuis PostgreSQL, en révélant le type
     * et la longueur (« value too long for type character varying(200) »).
     */
    public function testOverlongExchangeTitleIsRejectedWithoutLeakingSchema(): void
    {
        [$client, $h, $familyId] = $this->family('c6-title@example.com');

        $response = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => str_repeat('a', 300)],
            'headers' => $h,
        ]);
        self::assertSame(422, $response->getStatusCode());

        $detail = (string) ($response->toArray(false)['detail'] ?? '');
        self::assertStringNotContainsString('SQLSTATE', $detail);
        self::assertStringNotContainsString('character varying', $detail);
        self::assertStringContainsString('trop long', $detail);
    }

    /** C6 — même défaut sur la locale des préférences de notification (colonne `varchar(16)`). */
    public function testInvalidLocaleIsRejectedWithoutLeakingSchema(): void
    {
        [$client, $token] = $this->authenticatedClient('c6-locale@example.com');

        $response = $client->request('PUT', '/api/me/notification-prefs', [
            'json' => ['email' => [], 'locale' => '<script>zz</script>'],
            'headers' => $this->bearer($token),
        ]);
        self::assertSame(422, $response->getStatusCode());
        self::assertStringNotContainsString('SQLSTATE', (string) ($response->toArray(false)['detail'] ?? ''));
    }

    // ───────────────────────── C8 — message d'autorisation ─────────────────────────

    /** C8 — `Droit '{right}' requis.` : l'apostrophe ICU échappait le paramètre, jamais substitué. */
    public function testAuthorizationMessageInterpolatesTheRequiredRight(): void
    {
        [$client, $h, $familyId] = $this->family('c8-owner@example.com');
        $probe = $this->addMemberAndLogin($client, $familyId, 'c8-probe@example.com', custody: RightLevel::None);

        $response = $client->request('GET', '/api/families/'.$familyId.'/custody/pattern', [
            'headers' => $probe,
        ]);
        self::assertSame(403, $response->getStatusCode());

        $detail = (string) ($response->toArray(false)['detail'] ?? '');
        self::assertStringNotContainsString('{right}', $detail);
        self::assertStringContainsString('custody:view', $detail);
    }

    // ───────────────────── C9 / C5 — occurrences de série ─────────────────────

    /** C9 — `recurrenceDate` doit accepter l'instant ISO que l'API elle-même renvoie. */
    public function testRecurrenceDateAcceptsTheInstantTheApiReturns(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c9@example.com');
        $this->createSeries($client, $h, $familyId, $parentA, $childId);
        $occurrence = $this->occurrenceAt($client, $h, $familyId, '2026-09-17');

        $patch = $client->request('PATCH', '/api/events/'.$occurrence['id'].'?scope=occurrence', [
            'json' => ['recurrenceDate' => $occurrence['startsAt'], 'title' => 'Judo (gala)'],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $patch->getStatusCode(), (string) ($patch->toArray(false)['detail'] ?? ''));
        self::assertSame('Judo (gala)', $patch->toArray()['title']);
    }

    /** C9 — un format réellement invalide est distingué de « occurrence inexistante ». */
    public function testMalformedRecurrenceDateSaysSoInsteadOfDenyingTheOccurrence(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c9-bad@example.com');
        $master = $this->createSeries($client, $h, $familyId, $parentA, $childId);

        $patch = $client->request('PATCH', '/api/events/'.$master['id'].'?scope=occurrence', [
            'json' => ['recurrenceDate' => 'demain', 'title' => 'Judo'],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(422, $patch->getStatusCode());
        $detail = (string) ($patch->toArray(false)['detail'] ?? '');
        self::assertStringContainsString('AAAA-MM-JJ', $detail);
        self::assertStringNotContainsString('aucune occurrence', $detail);
    }

    /**
     * C5 — « toute la série (à partir de cette date) » laissait intacte l'occurrence déjà
     * personnalisée, y compris celle que l'utilisateur regardait en lançant l'édition.
     */
    public function testEditingWholeSeriesAlsoUpdatesAlreadyOverriddenOccurrences(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c5@example.com');
        $master = $this->createSeries($client, $h, $familyId, $parentA, $childId);
        $json = $h + ['Content-Type' => 'application/merge-patch+json'];

        // 1. L'occurrence du 17/09 est personnalisée (override).
        self::assertSame(200, $client->request('PATCH', '/api/events/'.$master['id'].'?scope=occurrence', [
            'json' => ['recurrenceDate' => '2026-09-17', 'title' => 'Judo (gala)'],
            'headers' => $json,
        ])->getStatusCode());

        // 2. Puis « toute la série » à partir de cette même occurrence.
        self::assertSame(200, $client->request('PATCH', '/api/events/'.$master['id'].'?scope=series', [
            'json' => ['recurrenceDate' => '2026-09-17', 'title' => 'Judo avancé'],
            'headers' => $json,
        ])->getStatusCode());

        $titles = [];
        foreach ($this->occurrences($client, $h, $familyId) as $occurrence) {
            $titles[substr($occurrence['startsAt'], 0, 10)] = $occurrence['title'];
        }

        self::assertSame('Judo', $titles['2026-09-03'] ?? null, 'Les occurrences antérieures au pivot sont inchangées.');
        self::assertSame('Judo avancé', $titles['2026-09-17'] ?? null, "L'occurrence pivot gardait son ancien titre (C5).");
        self::assertSame('Judo avancé', $titles['2026-09-24'] ?? null);
    }

    // ───────────────────────── C10 — portée de la clé d'idempotence ─────────────────────────

    /**
     * C10 — deux membres de la même famille employant la même `Idempotency-Key` : le second recevait
     * 201 **et la ressource du premier**, sa propre création étant silencieusement perdue.
     */
    public function testIdempotencyKeyIsScopedPerAuthorNotPerFamily(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c10-a@example.com');
        $other = $this->addMemberAndLogin($client, $familyId, 'c10-b@example.com', agenda: RightLevel::Edit);

        $key = ['Idempotency-Key' => 'meme-cle-pour-les-deux'];
        $mine = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->eventPayload($parentA, $childId, 'Événement de A'),
            'headers' => $h + $key,
        ]);
        self::assertSame(201, $mine->getStatusCode());

        $theirs = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->eventPayload($parentA, $childId, 'Événement de B'),
            'headers' => $other + $key,
        ]);
        self::assertSame(201, $theirs->getStatusCode());

        self::assertSame('Événement de B', $theirs->toArray()['title'], 'La création du second membre a été perdue (C10).');
        self::assertNotSame($mine->toArray()['id'], $theirs->toArray()['id']);
    }

    /** C10 — le rejeu de SA propre clé reste idempotent : la correction ne casse pas l'outbox. */
    public function testReplayingOwnKeyStillReturnsTheSameResource(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c10-replay@example.com');
        $headers = $h + ['Idempotency-Key' => 'ma-cle'];
        $payload = $this->eventPayload($parentA, $childId, 'Rendez-vous');

        $first = $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $payload, 'headers' => $headers]);
        $second = $client->request('POST', '/api/families/'.$familyId.'/events', ['json' => $payload, 'headers' => $headers]);

        self::assertSame(201, $first->getStatusCode(), (string) ($first->toArray(false)['detail'] ?? json_encode($first->toArray(false))));
        self::assertSame(201, $second->getStatusCode());
        self::assertSame($first->toArray()['id'], $second->toArray()['id']);
    }

    // ───────────────────────── C20 — verrou optimiste utilisable ─────────────────────────

    /** C20 — `expectedVersion` était accepté en écriture sans qu'aucune lecture n'expose `version`. */
    public function testEventReadsExposeVersionSoTheOptimisticLockIsUsable(): void
    {
        [$client, $h, $familyId, $parentA, $childId] = $this->familyWithChild('c20@example.com');
        $created = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->eventPayload($parentA, $childId, 'Rendez-vous'),
            'headers' => $h,
        ])->toArray();

        self::assertArrayHasKey('version', $created);
        $occurrence = $this->occurrences($client, $h, $familyId)[0];
        self::assertArrayHasKey('version', $occurrence, 'Aucune lecture n\'exposait la version (C20).');

        // La version lue permet réellement d'armer le verrou…
        $ok = $client->request('PATCH', '/api/events/'.$created['id'], [
            'json' => ['title' => 'Rendez-vous décalé', 'expectedVersion' => $occurrence['version']],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $ok->getStatusCode());

        // …et une version périmée est bien refusée.
        $stale = $client->request('PATCH', '/api/events/'.$created['id'], [
            'json' => ['title' => 'Concurrent', 'expectedVersion' => $occurrence['version']],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(409, $stale->getStatusCode());
    }

    // ───────────────────────── C3 — contrat OpenAPI ─────────────────────────

    /**
     * C3 — aucune opération ne déclarait ses paramètres de requête, alors que le contrat est la
     * source du client Dart mobile : l'agenda n'était pas affichable depuis le seul contrat.
     */
    public function testOpenApiDeclaresStructuringQueryParameters(): void
    {
        $client = self::createClient();
        $spec = $client->request('GET', '/api/docs', ['headers' => ['Accept' => 'application/vnd.openapi+json']])->toArray();

        $expected = [
            '/api/families/{familyId}/events' => ['get' => ['from', 'to', 'category', 'overlay']],
            '/api/events/{id}' => [
                'patch' => ['scope'],
                'delete' => ['scope', 'recurrenceDate', 'from'],
            ],
            '/api/families/{familyId}/custody/days' => ['get' => ['from', 'to']],
            '/api/families/{familyId}/custody/stats' => ['get' => ['from', 'to']],
            '/api/families/{familyId}/exchanges' => ['get' => ['status']],
        ];

        foreach ($expected as $path => $methods) {
            foreach ($methods as $method => $names) {
                $declared = array_column(
                    array_filter(
                        $spec['paths'][$path][$method]['parameters'] ?? [],
                        static fn (array $p): bool => 'query' === ($p['in'] ?? null),
                    ),
                    'name',
                );
                foreach ($names as $name) {
                    self::assertContains($name, $declared, "{$method} {$path} ne déclare pas `?{$name}` (C3).");
                }
            }
        }
    }

    // ───────────────────────────── outillage ─────────────────────────────

    /** @return array{0: Client, 1: array<string,string>, 2: string} */
    private function family(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Revue', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];

        return [$client, $auth + ['X-Family-Id' => $familyId], $familyId];
    }

    /** @return array{0: Client, 1: array<string,string>, 2: string, 3: string, 4: string} */
    private function familyWithChild(string $email): array
    {
        [$client, $scoped, $familyId] = $this->family($email);
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $scoped])->toArray();

        $child = (new Child())->setFamily($this->findFamily($familyId))->setFirstName('Léa');
        $this->em()->persist($child);
        $this->em()->flush();

        return [$client, $scoped, $familyId, $members[0]['id'], (string) $child->getId()];
    }

    /**
     * Ajoute un membre à la famille, le connecte et renvoie ses en-têtes scopés.
     *
     * @return array<string, string>
     */
    private function addMemberAndLogin(
        Client $client,
        string $familyId,
        string $email,
        ?RightLevel $agenda = null,
        ?RightLevel $custody = null,
    ): array {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())
            ->setUser($user)
            ->setFamily($this->findFamily($familyId))
            ->setRole(MembershipRole::StepParent)
            ->setStatus(MembershipStatus::Active);
        if (null !== $agenda) {
            $membership->addRight(new Right(RightDomain::Agenda, $agenda));
        }
        if (null !== $custody) {
            $membership->addRight(new Right(RightDomain::Custody, $custody));
        }
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();

        return $this->bearer($this->login($client, $email)) + ['X-Family-Id' => $familyId];
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
     * @return array<string, mixed>
     */
    private function eventPayload(string $parentA, string $childId, string $title): array
    {
        return [
            'title' => $title,
            'category' => 'autre',
            'responsibleMembershipId' => $parentA,
            'childIds' => [$childId],
            'wholeFamily' => false,
            'startsAt' => '2026-09-03T17:00:00+02:00',
            'endsAt' => '2026-09-03T18:00:00+02:00',
        ];
    }

    /**
     * Série « Judo » hebdomadaire de 6 occurrences à partir du jeudi 03/09/2026 — le jeu d'essai de
     * la revue.
     *
     * @param array<string, string> $h
     *
     * @return array<string, mixed>
     */
    private function createSeries(Client $client, array $h, string $familyId, string $parentA, string $childId): array
    {
        $response = $client->request('POST', '/api/families/'.$familyId.'/events', [
            'json' => $this->eventPayload($parentA, $childId, 'Judo') + [
                'recurrence' => ['freq' => 'weekly', 'count' => 6],
            ],
            'headers' => $h,
        ]);
        self::assertSame(201, $response->getStatusCode(), (string) ($response->toArray(false)['detail'] ?? ''));

        return $response->toArray();
    }

    /**
     * @param array<string, string> $h
     *
     * @return list<array<string, mixed>>
     */
    private function occurrences(Client $client, array $h, string $familyId): array
    {
        $response = $client->request(
            'GET',
            '/api/families/'.$familyId.'/events?from=2026-09-01&to=2026-10-31',
            ['headers' => $h],
        );
        self::assertSame(200, $response->getStatusCode());

        /** @var list<array<string, mixed>> $list */
        $list = $response->toArray();

        return $list;
    }

    /**
     * @param array<string, string> $h
     *
     * @return array<string, mixed>
     */
    private function occurrenceAt(Client $client, array $h, string $familyId, string $date): array
    {
        foreach ($this->occurrences($client, $h, $familyId) as $occurrence) {
            if (str_starts_with((string) $occurrence['startsAt'], $date)) {
                return $occurrence;
            }
        }

        self::fail("Aucune occurrence au {$date}.");
    }
}
