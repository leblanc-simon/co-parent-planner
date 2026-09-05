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
 * Lot 6 — endpoints échanges : création (discussion / décision), validations (422 RFC 7807), liste
 * filtrée + badge, détail, fil de messages (chiffrement 🔒), décision (consensus / désaccord /
 * forçage / 409), idempotence, autorisations (Voter exchanges), scope X-Family-Id.
 */
final class ExchangeApiLot6Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string, 3: string} client, headers scopés, familyId, childId */
    private function family(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Échanges', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        $scoped = $auth + ['X-Family-Id' => $familyId];
        $childId = $this->addChild($familyId, 'Léa');

        return [$client, $scoped, $familyId, $childId];
    }

    private function addChild(string $familyId, string $firstName): string
    {
        $child = (new Child())->setFamily($this->findFamily($familyId))->setFirstName($firstName);
        $this->em()->persist($child);
        $this->em()->flush();

        return (string) $child->getId();
    }

    private function addMember(string $familyId, string $email, MembershipRole $role, ?RightLevel $exchanges): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($this->findFamily($familyId))->setRole($role)->setStatus(MembershipStatus::Active);
        if (null !== $exchanges) {
            $membership->addRight(new Right(RightDomain::Exchanges, $exchanges));
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

    // ---- Création ----

    public function testCreateDiscussion(): void
    {
        [$client, $h, $familyId] = $this->family('ex-disc@example.com');
        $res = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Organisation vacances'],
            'headers' => $h,
        ]);
        self::assertSame(201, $res->getStatusCode());
        $body = $res->toArray();
        self::assertSame('discussion', $body['type']);
        self::assertSame('open', $body['status']);
        self::assertSame([], $body['options']);
    }

    public function testCreateDecisionWithOptions(): void
    {
        [$client, $h, $familyId, $childId] = $this->family('ex-dec@example.com');
        $res = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => [
                'type' => 'decision',
                'title' => 'Centre aéré de juillet',
                'childId' => $childId,
                'deadline' => '2026-06-30',
                'options' => [
                    ['label' => 'Centre A', 'sub' => 'proche école'],
                    ['label' => 'Centre B'],
                ],
            ],
            'headers' => $h,
        ]);
        self::assertSame(201, $res->getStatusCode());
        $body = $res->toArray();
        self::assertSame('decision', $body['type']);
        self::assertCount(2, $body['options']);
        self::assertSame('Centre A', $body['options'][0]['label']);
        self::assertSame(0, $body['options'][0]['position']);
        self::assertSame($childId, $body['childId']);
    }

    public function testDecisionRequiresTwoOptions(): void
    {
        [$client, $h, $familyId] = $this->family('ex-1opt@example.com');
        $res = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'X', 'options' => [['label' => 'Seule']]],
            'headers' => $h,
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testTitleRequired(): void
    {
        [$client, $h, $familyId] = $this->family('ex-notitle@example.com');
        $res = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => '   '],
            'headers' => $h,
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    // ---- Liste + badge ----

    public function testListFilterByStatusAndOpenCount(): void
    {
        [$client, $h, $familyId] = $this->family('ex-list@example.com');
        foreach (['A', 'B'] as $t) {
            $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
                'json' => ['type' => 'discussion', 'title' => 'Sujet '.$t],
                'headers' => $h,
            ]);
        }
        $open = $client->request('GET', '/api/families/'.$familyId.'/exchanges?status=open', ['headers' => $h])->toArray();
        self::assertCount(2, $open);
        $decided = $client->request('GET', '/api/families/'.$familyId.'/exchanges?status=decided', ['headers' => $h])->toArray();
        self::assertCount(0, $decided);
    }

    // ---- Messages + chiffrement ----

    public function testPostMessageIsEncryptedAtRest(): void
    {
        [$client, $h, $familyId] = $this->family('ex-msg@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Fil'],
            'headers' => $h,
        ])->toArray()['id'];

        $secret = 'Rendez-vous secret pédiatre lundi';
        $res = $client->request('POST', '/api/exchanges/'.$exchangeId.'/messages', [
            'json' => ['text' => $secret],
            'headers' => $h,
        ]);
        self::assertSame(201, $res->getStatusCode());
        self::assertSame($secret, $res->toArray()['text']); // clair pour l'appelant autorisé

        // Aucun clair en base (dump brut) : la colonne text est chiffrée.
        $raw = $this->em()->getConnection()->fetchOne('SELECT text FROM messages LIMIT 1');
        self::assertIsString($raw);
        self::assertStringNotContainsString('pédiatre', $raw);
        self::assertStringNotContainsString($secret, $raw);

        // Détail : le fil contient le message en clair.
        $detail = $client->request('GET', '/api/exchanges/'.$exchangeId, ['headers' => $h])->toArray();
        self::assertSame(1, $detail['messageCount']);
        self::assertSame($secret, $detail['messages'][0]['text']);
    }

    public function testMessageEmptyRejected(): void
    {
        [$client, $h, $familyId] = $this->family('ex-emptymsg@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Fil'],
            'headers' => $h,
        ])->toArray()['id'];
        $res = $client->request('POST', '/api/exchanges/'.$exchangeId.'/messages', [
            'json' => ['text' => '  '],
            'headers' => $h,
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testMessageIdempotency(): void
    {
        [$client, $h, $familyId] = $this->family('ex-idem@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Fil'],
            'headers' => $h,
        ])->toArray()['id'];
        $key = ['Idempotency-Key' => 'msg-key-42'];
        $first = $client->request('POST', '/api/exchanges/'.$exchangeId.'/messages', [
            'json' => ['text' => 'Bonjour'], 'headers' => $h + $key,
        ])->toArray();
        $second = $client->request('POST', '/api/exchanges/'.$exchangeId.'/messages', [
            'json' => ['text' => 'Bonjour'], 'headers' => $h + $key,
        ])->toArray();
        self::assertSame($first['id'], $second['id']); // rejeu : aucun doublon
    }

    // ---- Décision : cycle de vie ----

    public function testDecisionConsensusBasculesToDecided(): void
    {
        [$client, $hA, $familyId] = $this->family('ex-cons-a@example.com');
        $this->addMember($familyId, 'ex-cons-b@example.com', MembershipRole::Parent, RightLevel::Edit);
        $hB = $this->bearer($this->login($client, 'ex-cons-b@example.com')) + ['X-Family-Id' => $familyId];

        $decision = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'Choix', 'options' => [['label' => 'O1'], ['label' => 'O2']]],
            'headers' => $hA,
        ])->toArray();
        $exchangeId = $decision['id'];
        $o1 = $decision['options'][0]['id'];

        // A vote O1 : électorat = 2, un seul a voté → reste ouvert.
        $afterA = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', [
            'json' => ['optionId' => $o1], 'headers' => $hA,
        ])->toArray();
        self::assertSame('open', $afterA['status']);

        // B vote O1 : consensus → decided.
        $afterB = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', [
            'json' => ['optionId' => $o1], 'headers' => $hB,
        ])->toArray();
        self::assertSame('decided', $afterB['status']);
        self::assertSame($o1, $afterB['decidedOptionId']);

        // Re-décider une décision tranchée → 409.
        $again = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', [
            'json' => ['optionId' => $o1], 'headers' => $hA,
        ]);
        self::assertSame(409, $again->getStatusCode());
    }

    public function testDisagreementStaysOpenThenForcedViaPatch(): void
    {
        [$client, $hA, $familyId] = $this->family('ex-dis-a@example.com');
        $this->addMember($familyId, 'ex-dis-b@example.com', MembershipRole::Parent, RightLevel::Edit);
        $hB = $this->bearer($this->login($client, 'ex-dis-b@example.com')) + ['X-Family-Id' => $familyId];

        $decision = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'Choix', 'options' => [['label' => 'O1'], ['label' => 'O2']]],
            'headers' => $hA,
        ])->toArray();
        $exchangeId = $decision['id'];
        [$o1, $o2] = [$decision['options'][0]['id'], $decision['options'][1]['id']];

        $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', ['json' => ['optionId' => $o1], 'headers' => $hA]);
        $afterB = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', ['json' => ['optionId' => $o2], 'headers' => $hB])->toArray();
        self::assertSame('open', $afterB['status']); // désaccord → reste ouvert

        // Forçage explicite par un membre edit.
        $forced = $client->request('PATCH', '/api/exchanges/'.$exchangeId, [
            'json' => ['decidedOptionId' => $o1],
            'headers' => $hA + ['Content-Type' => 'application/merge-patch+json'],
        ])->toArray();
        self::assertSame('decided', $forced['status']);
        self::assertSame($o1, $forced['decidedOptionId']);
    }

    public function testDecideOnDiscussionRejected(): void
    {
        [$client, $h, $familyId] = $this->family('ex-decdisc@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Pas une décision'],
            'headers' => $h,
        ])->toArray()['id'];
        // Une discussion n'a pas d'option ; decide avec un uuid quelconque → 422 (pas une décision).
        $res = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', [
            'json' => ['optionId' => (string) Uuid::v7()], 'headers' => $h,
        ]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testCloseDiscussionViaPatch(): void
    {
        [$client, $h, $familyId] = $this->family('ex-close@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'À clore'],
            'headers' => $h,
        ])->toArray()['id'];
        $res = $client->request('PATCH', '/api/exchanges/'.$exchangeId, [
            'json' => ['status' => 'decided'],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $res->getStatusCode());
        self::assertSame('decided', $res->toArray()['status']);
    }

    // ---- Autorisations & scope ----

    public function testViewMemberCannotCreate(): void
    {
        [$client, , $familyId] = $this->family('ex-owner@example.com');
        $this->addMember($familyId, 'ex-view@example.com', MembershipRole::StepParent, RightLevel::View);
        $hView = $this->bearer($this->login($client, 'ex-view@example.com')) + ['X-Family-Id' => $familyId];

        $res = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Interdit'], 'headers' => $hView,
        ]);
        self::assertSame(403, $res->getStatusCode());
    }

    public function testNoneMemberCannotList(): void
    {
        [$client, , $familyId] = $this->family('ex-owner2@example.com');
        $this->addMember($familyId, 'ex-none@example.com', MembershipRole::StepParent, null);
        $hNone = $this->bearer($this->login($client, 'ex-none@example.com')) + ['X-Family-Id' => $familyId];

        $res = $client->request('GET', '/api/families/'.$familyId.'/exchanges', ['headers' => $hNone]);
        self::assertSame(403, $res->getStatusCode());
    }

    public function testCrossFamilyExchangeIsHidden(): void
    {
        [$client, $hA, $familyIdA] = $this->family('ex-f1@example.com');
        $exchangeId = $client->request('POST', '/api/families/'.$familyIdA.'/exchanges', [
            'json' => ['type' => 'discussion', 'title' => 'Privé F1'], 'headers' => $hA,
        ])->toArray()['id'];

        // Utilisateur B, sa propre famille F2 (même client, JWT bearer) : ne doit pas voir l'échange de F1.
        $this->createActiveUser('ex-f2@example.com');
        $authB = $this->bearer($this->login($client, 'ex-f2@example.com'));
        $familyIdB = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Autre famille', 'type' => 'separated'],
            'headers' => $authB,
        ])->toArray()['id'];
        $hB = $authB + ['X-Family-Id' => $familyIdB];

        $res = $client->request('GET', '/api/exchanges/'.$exchangeId, ['headers' => $hB]);
        self::assertSame(404, $res->getStatusCode());
    }

    // ---- État du tour de vote (revue UX des échanges, X1/X2) ----

    /**
     * Ce que l'écran ne disait pas : selon que l'autre parent a voté ou non, le même clic est un
     * brouillon modifiable ou une signature définitive. Le serveur, qui porte déjà la règle
     * (`DecisionEngine` : consensus de TOUS les électeurs), l'énonce désormais.
     */
    public function testVoteTurnIsExposedToTheCaller(): void
    {
        [$client, $hA, $familyId] = $this->family('ex-turn-a@example.com');
        $this->addMember($familyId, 'ex-turn-b@example.com', MembershipRole::Parent, RightLevel::Edit);
        $hB = $this->bearer($this->login($client, 'ex-turn-b@example.com')) + ['X-Family-Id' => $familyId];

        $decision = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'Colonie', 'options' => [['label' => 'Montagne'], ['label' => 'Mer']]],
            'headers' => $hA,
        ])->toArray();
        $exchangeId = $decision['id'];
        $o1 = $decision['options'][0]['id'];

        // Personne n'a voté : A attend B, et son vote ne tranchera pas.
        $beforeA = $client->request('GET', '/api/exchanges/'.$exchangeId, ['headers' => $hA])->toArray();
        self::assertNull($beforeA['myVotedOptionId']);
        self::assertFalse($beforeA['myVoteMaySettle']);
        self::assertCount(1, $beforeA['awaitingVoters']);

        // A vote : son choix lui est rappelé, et il attend toujours B.
        $afterA = $client->request('POST', '/api/exchanges/'.$exchangeId.'/decide', [
            'json' => ['optionId' => $o1], 'headers' => $hA,
        ])->toArray();
        self::assertSame($o1, $afterA['myVotedOptionId']);
        self::assertFalse($afterA['myVoteMaySettle']);
        self::assertTrue($afterA['options'][0]['myVote']);
        self::assertFalse($afterA['options'][1]['myVote']);

        // Vu par B : c'est à lui de jouer, et son vote PEUT trancher.
        $forB = $client->request('GET', '/api/exchanges/'.$exchangeId, ['headers' => $hB])->toArray();
        self::assertTrue($forB['myVoteMaySettle']);
        self::assertSame([], $forB['awaitingVoters']);
        // Le vote de A ne lui est pas attribué : on dit qui manque, jamais pour quoi chacun a voté.
        self::assertNull($forB['myVotedOptionId']);
        self::assertFalse($forB['options'][0]['myVote']);
    }

    /**
     * X2 — trancher une décision que personne ne vote. `ExchangeWriter` l'acceptait depuis toujours ;
     * aucun écran ne l'offrait, et une décision non votée restait ouverte indéfiniment.
     */
    public function testADecisionCanBeSettledWithoutAnyVote(): void
    {
        [$client, $h, $familyId] = $this->family('ex-settle@example.com');
        $decision = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'Colonie', 'options' => [['label' => 'Montagne'], ['label' => 'Mer']]],
            'headers' => $h,
        ])->toArray();
        $o2 = $decision['options'][1]['id'];

        $settled = $client->request('PATCH', '/api/exchanges/'.$decision['id'], [
            'json' => ['decidedOptionId' => $o2],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ])->toArray();

        self::assertSame('decided', $settled['status']);
        self::assertSame($o2, $settled['decidedOptionId']);
        // Une décision tranchée n'attend plus personne.
        self::assertSame([], $settled['awaitingVoters']);
        self::assertFalse($settled['myVoteMaySettle']);
    }

    /** Clore une décision SANS retenir d'option n'a pas de sens : le serveur le refuse. */
    public function testADecisionCannotBeClosedWithoutAWinningOption(): void
    {
        [$client, $h, $familyId] = $this->family('ex-settle-none@example.com');
        $decision = $client->request('POST', '/api/families/'.$familyId.'/exchanges', [
            'json' => ['type' => 'decision', 'title' => 'Colonie', 'options' => [['label' => 'A'], ['label' => 'B']]],
            'headers' => $h,
        ])->toArray();

        $res = $client->request('PATCH', '/api/exchanges/'.$decision['id'], [
            'json' => ['status' => 'decided'],
            'headers' => $h + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(422, $res->getStatusCode());
    }
}
