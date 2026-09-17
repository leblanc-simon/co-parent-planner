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
use App\Rgpd\Anonymizer;
use App\Rgpd\Export\UserDataExporter;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Lot 10 — endpoints Dépenses : activation du module, création (50/50, pourcentages, montants),
 * validations (422), liste et filtres, règlement idempotent, suppression, solde, rappel et cooldown,
 * autorisations (Voter `expenses`, interdiction stricte enfant) et isolation cross-famille.
 */
final class ExpenseApiLot10Test extends ApiAuthTestCase
{
    use ClockSensitiveTrait;

    /**
     * Famille **avec le module activé** et deux parents. L'activation passe par l'API réelle
     * (`PATCH /api/families/{id}`, US-DEP-6) plutôt que par un `setExpensesEnabled()` en base :
     * c'est le chemin qu'emprunte un administrateur, et il doit rester exercé.
     *
     * @return array{0: Client, 1: array<string,string>, 2: string, 3: string, 4: string, 5: string}
     *                                                                                               client, en-têtes scopés, familyId, parentA, parentB, childId
     */
    private function family(string $email, bool $enableModule = true): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Dépenses', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        $scoped = $auth + ['X-Family-Id' => $familyId];

        if ($enableModule) {
            $res = $client->request('PATCH', '/api/families/'.$familyId, [
                'json' => ['expensesEnabled' => true],
                'headers' => $scoped + ['Content-Type' => 'application/merge-patch+json'],
            ]);
            self::assertSame(200, $res->getStatusCode());
        }

        $members = $client->request('GET', '/api/families/'.$familyId.'/members', ['headers' => $scoped])->toArray();
        $parentA = $members[0]['id'];
        $parentB = $this->addMember($familyId, 'b-'.$email, MembershipRole::Parent, null);
        $childId = $this->addChild($familyId, 'Léa');

        return [$client, $scoped, $familyId, $parentA, $parentB, $childId];
    }

    private function addChild(string $familyId, string $firstName): string
    {
        $child = (new Child())->setFamily($this->findFamily($familyId))->setFirstName($firstName);
        $this->em()->persist($child);
        $this->em()->flush();

        return (string) $child->getId();
    }

    private function addMember(string $familyId, string $email, MembershipRole $role, ?RightLevel $expenses): string
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($this->findFamily($familyId))
            ->setRole($role)->setStatus(MembershipStatus::Active);
        if (null !== $expenses) {
            $membership->addRight(new Right(RightDomain::Expenses, $expenses));
        }
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();

        return (string) $membership->getId();
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
    private function payload(string $payer, string $childId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Cantine septembre',
            'category' => 'scolarite',
            'payerMembershipId' => $payer,
            'amountCents' => 10000,
            'expenseDate' => '2026-09-04',
            'childIds' => [$childId],
        ], $overrides);
    }

    // ---- Création ------------------------------------------------------------------------------

    public function testCreateWithDefaultFiftyFiftyShare(): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-create@example.com');

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId),
            'headers' => $h,
        ]);

        self::assertSame(201, $res->getStatusCode());
        $body = $res->toArray();
        self::assertSame('Cantine septembre', $body['title']);
        self::assertSame(10000, $body['amountCents']);
        self::assertSame('EUR', $body['currency']);
        self::assertFalse($body['settled']);
        self::assertSame([$childId], $body['childIds']);

        // Le 50/50 est matérialisé, pas implicite : deux parts persistées, montants effectifs égaux.
        self::assertCount(2, $body['shares']);
        $effective = array_column($body['shares'], 'effectiveCents');
        self::assertSame(10000, array_sum($effective));
        self::assertSame([5000, 5000], $effective);
        self::assertContains($parentB, array_column($body['shares'], 'membershipId'));
        self::assertNotSame('', $body['payerName']);
    }

    public function testCreateWithPercentShares(): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-pct@example.com');

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId, [
                'amountCents' => 1001,
                'shares' => [
                    ['membershipId' => $parentA, 'shareType' => 'percent', 'value' => 70],
                    ['membershipId' => $parentB, 'shareType' => 'percent', 'value' => 30],
                ],
            ]),
            'headers' => $h,
        ]);

        self::assertSame(201, $res->getStatusCode());
        // L'arrondi ne perd pas de centime : 70 % de 10,01 € + 30 % = 10,01 € exactement.
        self::assertSame(1001, array_sum(array_column($res->toArray()['shares'], 'effectiveCents')));
    }

    public function testCreateWithAmountSharesKeepsValuesUntouched(): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-amt@example.com');

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId, [
                'amountCents' => 1000,
                'shares' => [
                    ['membershipId' => $parentA, 'shareType' => 'amount', 'value' => 333],
                    ['membershipId' => $parentB, 'shareType' => 'amount', 'value' => 667],
                ],
            ]),
            'headers' => $h,
        ]);

        self::assertSame(201, $res->getStatusCode());
        self::assertSame([333, 667], array_column($res->toArray()['shares'], 'effectiveCents'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(array $overrides, string $expectedFragment): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-invalid-'.md5($expectedFragment).'@example.com');
        $overrides = $this->resolvePlaceholders($overrides, $parentA, $parentB, $childId);

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId, $overrides),
            'headers' => $h,
        ]);

        self::assertSame(422, $res->getStatusCode());
        // Sur le `detail` décodé, jamais sur le corps brut : le JSON échappe les accents en \uXXXX,
        // et une assertion sur la chaîne brute ne peut donc pas contenir « é ».
        self::assertStringContainsString($expectedFragment, (string) ($res->toArray(false)['detail'] ?? ''));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'montant nul' => [['amountCents' => 0], 'supérieur à zéro'];
        yield 'montant négatif' => [['amountCents' => -100], 'supérieur à zéro'];
        yield 'montant hors borne' => [['amountCents' => 100_000_001], 'maximum autorisé'];
        yield 'catégorie inconnue' => [['category' => 'vacances'], 'Catégorie'];
        yield 'libellé vide' => [['title' => '   '], 'libellé'];
        yield 'aucun enfant' => [['childIds' => []], 'au moins un enfant'];
        yield 'date impossible' => [['expenseDate' => '2026-02-30'], 'Date'];
        yield '29 février non bissextile' => [['expenseDate' => '2027-02-29'], 'Date'];
        yield 'pourcentages ne sommant pas à 100' => [
            ['shares' => [['membershipId' => '@A', 'shareType' => 'percent', 'value' => 60], ['membershipId' => '@B', 'shareType' => 'percent', 'value' => 30]]],
            '100',
        ];
        yield 'montants ne sommant pas au total' => [
            ['shares' => [['membershipId' => '@A', 'shareType' => 'amount', 'value' => 100], ['membershipId' => '@B', 'shareType' => 'amount', 'value' => 100]]],
            'égale au total',
        ];
        yield 'types de parts mélangés' => [
            ['shares' => [['membershipId' => '@A', 'shareType' => 'percent', 'value' => 50], ['membershipId' => '@B', 'shareType' => 'amount', 'value' => 5000]]],
            "l'un ou l'autre",
        ];
        yield 'membre dupliqué dans le partage' => [
            ['shares' => [['membershipId' => '@A', 'shareType' => 'percent', 'value' => 50], ['membershipId' => '@A', 'shareType' => 'percent', 'value' => 50]]],
            'deux fois',
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function resolvePlaceholders(array $overrides, string $parentA, string $parentB, string $childId): array
    {
        if (!isset($overrides['shares'])) {
            return $overrides;
        }
        \assert(\is_array($overrides['shares']));
        $overrides['shares'] = array_map(
            static fn (array $share): array => array_merge($share, [
                'membershipId' => match ($share['membershipId']) {
                    '@A' => $parentA,
                    '@B' => $parentB,
                    default => $share['membershipId'],
                },
            ]),
            $overrides['shares'],
        );
        unset($childId);

        return $overrides;
    }

    /** Le payeur doit être un parent : un beau-parent habilité saisit, mais n'avance pas l'argent. */
    public function testStepParentCannotBePayer(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-payer@example.com');
        $stepParent = $this->addMember($familyId, 'step-payer@example.com', MembershipRole::StepParent, RightLevel::Edit);

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($stepParent, $childId),
            'headers' => $h,
        ]);

        self::assertSame(422, $res->getStatusCode());
        self::assertStringContainsString('parent actif', (string) ($res->toArray(false)['detail'] ?? ''));
        unset($parentA);
    }

    /** Un enfant d'une autre famille est refusé — et rien dans la réponse ne confirme son existence. */
    public function testChildFromAnotherFamilyIsRejected(): void
    {
        [$client, $h, $familyId, $parentA] = $this->family('dep-child-a@example.com');
        [, , $otherFamilyId] = $this->family('dep-child-b@example.com');
        $foreignChild = $this->addChild($otherFamilyId, 'Inconnu');

        $res = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $foreignChild),
            'headers' => $h,
        ]);

        self::assertSame(422, $res->getStatusCode());
    }

    // ---- Activation du module (US-DEP-6) -------------------------------------------------------

    /** Module désactivé : l'API n'existe pas pour cette famille — 404, même pour un parent admin. */
    public function testModuleDisabledHidesTheWholeApi(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-off@example.com', enableModule: false);

        self::assertSame(404, $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->getStatusCode());
        self::assertSame(404, $client->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->getStatusCode());
        self::assertSame(404, $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId),
            'headers' => $h,
        ])->getStatusCode());
    }

    /** Désactiver le module conserve les données : elles réapparaissent à la réactivation (§4). */
    public function testDisablingModuleKeepsDataForLater(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-toggle@example.com');
        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h]);

        $patch = $h + ['Content-Type' => 'application/merge-patch+json'];
        $client->request('PATCH', '/api/families/'.$familyId, ['json' => ['expensesEnabled' => false], 'headers' => $patch]);
        self::assertSame(404, $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->getStatusCode());

        $client->request('PATCH', '/api/families/'.$familyId, ['json' => ['expensesEnabled' => true], 'headers' => $patch]);
        self::assertCount(1, $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->toArray());
    }

    // ---- Autorisations -------------------------------------------------------------------------

    /** Interdiction stricte enfant : aucun `Right` ne rouvre le domaine (cdc §2.2). */
    public function testChildIsDeniedEvenWithExplicitRight(): void
    {
        [$client, , $familyId] = $this->family('dep-child-deny@example.com');
        $this->addMember($familyId, 'kid@example.com', MembershipRole::Child, RightLevel::Edit);

        $childClient = self::createClient();
        $childClient->disableReboot();
        $h = $this->bearer($this->login($childClient, 'kid@example.com')) + ['X-Family-Id' => $familyId];

        self::assertSame(403, $childClient->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->getStatusCode());
        self::assertSame(403, $childClient->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->getStatusCode());
        unset($client);
    }

    /** `expenses:view` lit mais n'écrit pas. */
    public function testViewerCannotWrite(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-viewer@example.com');
        $expenseId = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId), 'headers' => $h,
        ])->toArray()['id'];
        $this->addMember($familyId, 'viewer@example.com', MembershipRole::StepParent, RightLevel::View);

        $viewer = self::createClient();
        $viewer->disableReboot();
        $vh = $this->bearer($this->login($viewer, 'viewer@example.com')) + ['X-Family-Id' => $familyId];

        self::assertSame(200, $viewer->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $vh])->getStatusCode());
        self::assertSame(403, $viewer->request('PATCH', '/api/expenses/'.$expenseId, [
            'json' => ['settled' => true],
            'headers' => $vh + ['Content-Type' => 'application/merge-patch+json'],
        ])->getStatusCode());
        self::assertSame(403, $viewer->request('DELETE', '/api/expenses/'.$expenseId, ['headers' => $vh])->getStatusCode());
    }

    /** Anti-IDOR : une dépense d'une autre famille est introuvable, jamais « interdite ». */
    public function testExpenseOfAnotherFamilyIsNotFound(): void
    {
        [$clientA, $hA, $familyA, $parentA, , $childA] = $this->family('dep-idor-a@example.com');
        $expenseId = $clientA->request('POST', '/api/families/'.$familyA.'/expenses', [
            'json' => $this->payload($parentA, $childA), 'headers' => $hA,
        ])->toArray()['id'];

        [$clientB, $hB] = $this->family('dep-idor-b@example.com');

        self::assertSame(404, $clientB->request('PATCH', '/api/expenses/'.$expenseId, [
            'json' => ['settled' => true],
            'headers' => $hB + ['Content-Type' => 'application/merge-patch+json'],
        ])->getStatusCode());
        self::assertSame(404, $clientB->request('DELETE', '/api/expenses/'.$expenseId, ['headers' => $hB])->getStatusCode());
    }

    // ---- Liste & filtres -----------------------------------------------------------------------

    public function testListFiltersCumulate(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-list@example.com');
        $other = $this->addChild($familyId, 'Tom');

        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId, ['category' => 'scolarite', 'expenseDate' => '2026-09-01']), 'headers' => $h]);
        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $other, ['category' => 'vetements', 'title' => 'Manteau', 'expenseDate' => '2026-09-05']), 'headers' => $h]);

        $all = $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->toArray();
        self::assertCount(2, $all);
        self::assertSame('Manteau', $all[0]['title'], 'tri par date décroissante');

        self::assertCount(1, $client->request('GET', '/api/families/'.$familyId.'/expenses?category=vetements', ['headers' => $h])->toArray());
        self::assertCount(1, $client->request('GET', '/api/families/'.$familyId.'/expenses?childId='.$childId, ['headers' => $h])->toArray());
        self::assertCount(2, $client->request('GET', '/api/families/'.$familyId.'/expenses?settled=false', ['headers' => $h])->toArray());
        self::assertCount(0, $client->request('GET', '/api/families/'.$familyId.'/expenses?settled=true', ['headers' => $h])->toArray());
        self::assertCount(1, $client->request('GET', '/api/families/'.$familyId.'/expenses?category=vetements&childId='.$other, ['headers' => $h])->toArray());
    }

    /** Un filtre qui ne veut rien dire se signale (422) plutôt que de rendre une liste vide. */
    public function testInvalidFiltersAreRejected(): void
    {
        [$client, $h, $familyId] = $this->family('dep-filter@example.com');

        self::assertSame(422, $client->request('GET', '/api/families/'.$familyId.'/expenses?category=vacances', ['headers' => $h])->getStatusCode());
        self::assertSame(422, $client->request('GET', '/api/families/'.$familyId.'/expenses?settled=peut-etre', ['headers' => $h])->getStatusCode());
        self::assertSame(422, $client->request('GET', '/api/families/'.$familyId.'/expenses?childId=pas-un-uuid', ['headers' => $h])->getStatusCode());
    }

    // ---- Règlement, modification, suppression --------------------------------------------------

    public function testSettleIsIdempotent(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-settle@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];
        $patch = $h + ['Content-Type' => 'application/merge-patch+json'];

        $first = $client->request('PATCH', '/api/expenses/'.$id, ['json' => ['settled' => true], 'headers' => $patch]);
        self::assertSame(200, $first->getStatusCode());
        self::assertTrue($first->toArray()['settled']);

        // Rejouer le même règlement ne change rien et n'est pas une erreur (§4.3).
        $second = $client->request('PATCH', '/api/expenses/'.$id, ['json' => ['settled' => true], 'headers' => $patch]);
        self::assertSame(200, $second->getStatusCode());
        self::assertTrue($second->toArray()['settled']);

        // Une dépense réglée sort du solde mais reste au total engagé (§3.2/§3.3).
        $balance = $client->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->toArray();
        self::assertTrue($balance['balanced']);
        self::assertSame(10000, $balance['totalCents']);
        self::assertSame(10000, $balance['settledCents']);
        self::assertSame(0, $balance['unsettledCents']);
    }

    /**
     * Corps de merge-patch **rempli de `null`**, tel que l'envoie le client Dart généré : le modèle
     * sérialise toutes ses clés, y compris celles que l'utilisateur n'a pas touchées.
     *
     * Le serveur doit lire `null` comme « inchangé », jamais comme « efface ». Sans cette
     * convention, marquer une dépense réglée depuis le mobile viderait son libellé, son montant et
     * ses enfants — et le solde avec.
     */
    public function testNullFilledMergePatchFromMobileErasesNothing(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-mobile-patch@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        $res = $client->request('PATCH', '/api/expenses/'.$id, [
            'json' => [
                'title' => null,
                'category' => null,
                'payerMembershipId' => null,
                'amountCents' => null,
                'expenseDate' => null,
                'settled' => true,
                'childIds' => null,
                'shares' => null,
            ],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(200, $res->getStatusCode());
        $body = $res->toArray();
        self::assertTrue($body['settled']);
        self::assertSame('Cantine septembre', $body['title']);
        self::assertSame(10000, $body['amountCents']);
        self::assertSame('scolarite', $body['category']);
        self::assertSame([$childId], $body['childIds']);
        self::assertCount(2, $body['shares']);
        self::assertSame($parentA, $body['payerMembershipId']);
    }

    /** Changer le montant sans réajuster un partage en montants est refusé : les comptes ne tiendraient plus. */
    public function testAmountChangeInvalidatingFixedSharesIsRejected(): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-amt-patch@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', [
            'json' => $this->payload($parentA, $childId, [
                'amountCents' => 1000,
                'shares' => [
                    ['membershipId' => $parentA, 'shareType' => 'amount', 'value' => 500],
                    ['membershipId' => $parentB, 'shareType' => 'amount', 'value' => 500],
                ],
            ]),
            'headers' => $h,
        ])->toArray()['id'];

        $res = $client->request('PATCH', '/api/expenses/'.$id, [
            'json' => ['amountCents' => 2000],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(422, $res->getStatusCode());

        // Avec un partage réajusté dans la même requête, la modification passe.
        $ok = $client->request('PATCH', '/api/expenses/'.$id, [
            'json' => ['amountCents' => 2000, 'shares' => [
                ['membershipId' => $parentA, 'shareType' => 'amount', 'value' => 1500],
                ['membershipId' => $parentB, 'shareType' => 'amount', 'value' => 500],
            ]],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame([1500, 500], array_column($ok->toArray()['shares'], 'effectiveCents'));
    }

    /** Un partage en pourcentage suit le montant sans rien réajuster. */
    public function testPercentSharesFollowAmountChange(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-pct-patch@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        $res = $client->request('PATCH', '/api/expenses/'.$id, [
            'json' => ['amountCents' => 3000],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(200, $res->getStatusCode());
        self::assertSame([1500, 1500], array_column($res->toArray()['shares'], 'effectiveCents'));
    }

    public function testDeleteRemovesExpenseAndItsShares(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-delete@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        self::assertSame(204, $client->request('DELETE', '/api/expenses/'.$id, ['headers' => $h])->getStatusCode());
        self::assertCount(0, $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->toArray());
        self::assertSame(404, $client->request('DELETE', '/api/expenses/'.$id, ['headers' => $h])->getStatusCode());
    }

    // ---- Solde ---------------------------------------------------------------------------------

    public function testBalanceNamesDebtorAndCreditor(): void
    {
        [$client, $h, $familyId, $parentA, $parentB, $childId] = $this->family('dep-balance@example.com');
        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h]);

        $balance = $client->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->toArray();

        self::assertFalse($balance['balanced']);
        self::assertSame(5000, $balance['amountCents']);
        self::assertSame($parentB, $balance['debtorMembershipId']);
        self::assertSame($parentA, $balance['creditorMembershipId']);
        self::assertSame(0, array_sum(array_column($balance['net'], 'netCents')), 'conservation du solde');

        // Répartition par catégorie : toutes les catégories sont là, la mouvementée porte 100 %.
        $byCategory = array_column($balance['byCategory'], null, 'category');
        self::assertCount(5, $byCategory);
        self::assertSame(10000, $byCategory['scolarite']['amountCents']);
        self::assertSame(100, $byCategory['scolarite']['percent']);
        self::assertSame(0, $byCategory['sante']['amountCents']);
    }

    // ---- Rappel --------------------------------------------------------------------------------

    public function testReminderIsRateLimitedByCooldown(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-reminder@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];

        $first = $client->request('POST', '/api/expenses/'.$id.'/reminder', ['headers' => $h]);
        self::assertSame(200, $first->getStatusCode());
        self::assertNotNull($first->toArray()['reminderSentAt']);
        self::assertNotNull($first->toArray()['reminderAvailableAt'], 'le client sait quand le bouton redevient actif');

        $second = $client->request('POST', '/api/expenses/'.$id.'/reminder', ['headers' => $h]);
        self::assertSame(429, $second->getStatusCode());
        self::assertStringContainsString('72', (string) ($second->toArray(false)['detail'] ?? ''));
    }

    /** Relancer sur une dépense déjà réglée n'a pas de sens : 422 explicite plutôt qu'un e-mail inutile. */
    public function testReminderOnSettledExpenseIsRejected(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-reminder-settled@example.com');
        $id = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h])->toArray()['id'];
        $client->request('PATCH', '/api/expenses/'.$id, [
            'json' => ['settled' => true],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(422, $client->request('POST', '/api/expenses/'.$id.'/reminder', ['headers' => $h])->getStatusCode());
    }

    // ---- RGPD ----------------------------------------------------------------------------------

    /**
     * Suppression de compte : les dépenses **restent** (intégrité des comptes partagés, §4.9), mais
     * celles dont une partie a disparu sortent du solde et le disent. Les garder à moitié
     * afficherait une dette due à personne.
     */
    public function testAnonymizedParentKeepsExpensesButLeavesTheBalance(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-rgpd@example.com');
        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h]);

        $before = $client->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->toArray();
        self::assertSame(5000, $before['amountCents']);

        $this->anonymize('b-dep-rgpd@example.com');

        $list = $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->toArray();
        self::assertCount(1, $list, 'la dépense survit à la suppression du compte');
        self::assertSame(10000, $list[0]['amountCents']);

        $after = $client->request('GET', '/api/families/'.$familyId.'/expenses/balance', ['headers' => $h])->toArray();
        self::assertTrue($after['balanced']);
        self::assertSame(1, $after['excludedFromBalanceCount']);
        self::assertSame(10000, $after['totalCents'], 'le total engagé, lui, ne bouge pas');
    }

    /** Droit d'accès : les dépenses où le sujet est partie prenante figurent à son export (§4.9). */
    public function testRgpdExportIncludesExpensesOfTheSubject(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-export@example.com');
        $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $h]);

        $exporter = self::getContainer()->get(UserDataExporter::class);
        self::assertInstanceOf(UserDataExporter::class, $exporter);
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'dep-export@example.com']);
        self::assertInstanceOf(User::class, $user);

        $export = $exporter->build($user);

        self::assertCount(1, $export['expenses']);
        self::assertSame('Cantine septembre', $export['expenses'][0]['title']);
        self::assertTrue($export['expenses'][0]['paidBySubject']);
        self::assertSame(50, $export['expenses'][0]['ownShareValue']);
    }

    private function anonymize(string $email): void
    {
        $anonymizer = self::getContainer()->get(Anonymizer::class);
        self::assertInstanceOf(Anonymizer::class, $anonymizer);
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        $anonymizer->anonymize($user, null, null);
        $this->em()->clear();
    }

    // ---- Idempotence de l'outbox mobile --------------------------------------------------------

    /** Rejeu d'un `POST` avec la même `Idempotency-Key` : une seule dépense, la même réponse (§4.3). */
    public function testIdempotentCreationDoesNotDuplicate(): void
    {
        [$client, $h, $familyId, $parentA, , $childId] = $this->family('dep-idem@example.com');
        $headers = $h + ['Idempotency-Key' => 'outbox-42'];

        $first = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $headers]);
        $second = $client->request('POST', '/api/families/'.$familyId.'/expenses', ['json' => $this->payload($parentA, $childId), 'headers' => $headers]);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(201, $second->getStatusCode());
        self::assertSame($first->toArray()['id'], $second->toArray()['id']);
        self::assertCount(1, $client->request('GET', '/api/families/'.$familyId.'/expenses', ['headers' => $h])->toArray());
    }
}
