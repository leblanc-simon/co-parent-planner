<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
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
 * Lot 2 — enfants & notes : CRUD enfant, notes (ajout/liste, text chiffré), droits (403),
 * scope famille (404), mutation de note par non-auteur (403), bloc Accès & droits.
 */
final class ChildNotesLot2Test extends ApiAuthTestCase
{
    /** @return array{0: Client, 1: array<string,string>, 2: string} client, bearer admin, family id */
    private function adminWithFamily(string $email): array
    {
        [$client, $token] = $this->authenticatedClient($email);
        $auth = $this->bearer($token);
        $familyId = $client->request('POST', '/api/families', [
            'json' => ['name' => 'Famille Enfants', 'type' => 'separated'],
            'headers' => $auth,
        ])->toArray()['id'];
        self::assertIsString($familyId);

        return [$client, $auth, $familyId];
    }

    private function addMember(string $familyId, string $email, MembershipRole $role, ?RightLevel $childrenNotes): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $families = self::getContainer()->get(FamilyRepository::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        self::assertInstanceOf(FamilyRepository::class, $families);
        $family = $families->find(Uuid::fromString($familyId));
        self::assertInstanceOf(Family::class, $family);

        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($family)->setRole($role)->setStatus(MembershipStatus::Active);
        if (null !== $childrenNotes) {
            $membership->addRight(new Right(RightDomain::ChildrenNotes, $childrenNotes));
        }
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();
    }

    /** @param array<string, string> $auth */
    private function createChild(Client $client, array $auth, string $familyId): string
    {
        $response = $client->request('POST', '/api/families/'.$familyId.'/children', [
            'json' => ['firstName' => 'Léa', 'color' => '#5E7CB8', 'birthDate' => '2016-09-12', 'schoolLabel' => 'CE2', 'info' => [['label' => 'Pointure', 'value' => '34']]],
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(201, $response->getStatusCode());

        return $response->toArray()['id'];
    }

    public function testCreateListAndGetChildWithAge(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin1@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];

        $childId = $this->createChild($client, $auth, $familyId);

        $list = $client->request('GET', '/api/families/'.$familyId.'/children', ['headers' => $scoped]);
        self::assertSame(200, $list->getStatusCode());
        self::assertCount(1, $list->toArray());

        $get = $client->request('GET', '/api/children/'.$childId, ['headers' => $scoped]);
        self::assertSame(200, $get->getStatusCode());
        $body = $get->toArray();
        self::assertSame('Léa', $body['firstName']);
        self::assertSame('L', $body['initial']);
        self::assertIsInt($body['age']);
        self::assertSame('Pointure', $body['info'][0]['label']);
    }

    /**
     * Le prénom étant chiffré, la liste ne peut plus être triée par SQL : ce test garde l'ordre
     * alphabétique que `ORDER BY first_name` assurait, accents compris.
     */
    public function testChildrenAreListedAlphabeticallyDespiteEncryption(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-order@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];

        foreach (['Zoé', 'Ambre', 'Élise'] as $firstName) {
            $created = $client->request('POST', '/api/families/'.$familyId.'/children', [
                'json' => ['firstName' => $firstName],
                'headers' => $scoped,
            ]);
            self::assertSame(201, $created->getStatusCode());
        }

        $list = $client->request('GET', '/api/families/'.$familyId.'/children', ['headers' => $scoped]);
        self::assertSame(200, $list->getStatusCode());
        self::assertSame(
            ['Ambre', 'Élise', 'Zoé'],
            array_map(static fn (array $child): string => $child['firstName'], $list->toArray()),
        );
    }

    public function testAddAndListNotesEncrypted(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin2@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];
        $childId = $this->createChild($client, $auth, $familyId);

        $add = $client->request('POST', '/api/children/'.$childId.'/notes', [
            'json' => ['text' => 'RDV pris pour la rentrée'],
            'headers' => $scoped,
        ]);
        self::assertSame(201, $add->getStatusCode());
        self::assertSame('RDV pris pour la rentrée', $add->toArray()['text']);

        $notes = $client->request('GET', '/api/children/'.$childId.'/notes', ['headers' => $scoped]);
        self::assertSame(200, $notes->getStatusCode());
        self::assertSame('RDV pris pour la rentrée', $notes->toArray()[0]['text']);

        // Le texte est chiffré au repos : la valeur brute en base ne contient pas le clair.
        $raw = $this->em()->getConnection()->fetchOne('SELECT text FROM notes LIMIT 1');
        self::assertIsString($raw);
        self::assertStringNotContainsString('RDV pris', $raw);
        self::assertStringStartsWith('enc:', $raw);
    }

    public function testFutureBirthDateRejected(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin3@example.com');
        $response = $client->request('POST', '/api/families/'.$familyId.'/children', [
            'json' => ['firstName' => 'Futur', 'birthDate' => '2999-01-01'],
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(422, $response->getStatusCode());
    }

    public function testViewMemberCannotMutate(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin4@example.com');
        $childId = $this->createChild($client, $auth, $familyId);
        $this->addMember($familyId, 'c-view4@example.com', MembershipRole::StepParent, RightLevel::View);

        $viewClient = self::createClient();
        $viewClient->disableReboot();
        $viewToken = $this->login($viewClient, 'c-view4@example.com');
        $viewScoped = $this->bearer($viewToken) + ['X-Family-Id' => $familyId];

        // Lecture OK.
        self::assertSame(200, $viewClient->request('GET', '/api/children/'.$childId, ['headers' => $viewScoped])->getStatusCode());
        // Ajout de note refusé (NOTE_EDIT requis).
        $post = $viewClient->request('POST', '/api/children/'.$childId.'/notes', [
            'json' => ['text' => 'tentative'],
            'headers' => $viewScoped,
        ]);
        self::assertSame(403, $post->getStatusCode());
        // Création d'enfant refusée (CHILD_MANAGE requis).
        $create = $viewClient->request('POST', '/api/families/'.$familyId.'/children', [
            'json' => ['firstName' => 'X'],
            'headers' => $viewScoped,
        ]);
        self::assertSame(403, $create->getStatusCode());
    }

    public function testNoteMutationByNonAuthorForbidden(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin5@example.com');
        $childId = $this->createChild($client, $auth, $familyId);
        // Le beau-parent (edit) ajoute sa note.
        $this->addMember($familyId, 'c-sp5@example.com', MembershipRole::StepParent, RightLevel::Edit);
        $spClient = self::createClient();
        $spClient->disableReboot();
        $spToken = $this->login($spClient, 'c-sp5@example.com');
        $spScoped = $this->bearer($spToken) + ['X-Family-Id' => $familyId];

        $noteId = $spClient->request('POST', '/api/children/'.$childId.'/notes', [
            'json' => ['text' => 'note du beau-parent'],
            'headers' => $spScoped,
        ])->toArray()['id'];

        // Un AUTRE beau-parent edit ne peut pas éditer la note d'autrui.
        $this->addMember($familyId, 'c-sp5b@example.com', MembershipRole::StepParent, RightLevel::Edit);
        $sp2 = self::createClient();
        $sp2->disableReboot();
        $sp2Scoped = $this->bearer($this->login($sp2, 'c-sp5b@example.com')) + ['X-Family-Id' => $familyId];

        $patch = $sp2->request('PATCH', '/api/notes/'.$noteId, [
            'json' => ['text' => 'détournement'],
            'headers' => $sp2Scoped + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(403, $patch->getStatusCode());

        // L'admin (parent) peut modérer.
        $modo = $client->request('DELETE', '/api/notes/'.$noteId, ['headers' => $auth + ['X-Family-Id' => $familyId]]);
        self::assertSame(204, $modo->getStatusCode());
    }

    public function testChildOfAnotherFamilyIsNotFound(): void
    {
        [$clientA, $authA, $f1] = $this->adminWithFamily('c-a@example.com');
        $childId = $this->createChild($clientA, $authA, $f1);

        [$clientB, $authB, $f2] = $this->adminWithFamily('c-b@example.com');
        // B tente d'accéder à l'enfant de F1 avec son scope F2 → 404 non révélateur.
        $response = $clientB->request('GET', '/api/children/'.$childId, ['headers' => $authB + ['X-Family-Id' => $f2]]);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testOptimisticLockRejectsStaleUpdate(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-lock@example.com');
        $scoped = $auth + ['X-Family-Id' => $familyId];
        $childId = $this->createChild($client, $auth, $familyId);

        $version = $client->request('GET', '/api/children/'.$childId, ['headers' => $scoped])->toArray()['version'];
        self::assertSame(1, $version);

        // 1re édition avec la bonne version → 200 (version passe à 2).
        $ok = $client->request('PATCH', '/api/children/'.$childId, [
            'json' => ['schoolLabel' => 'CM1', 'expectedVersion' => 1],
            'headers' => $scoped + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $ok->getStatusCode());
        self::assertSame(2, $ok->toArray()['version']);

        // 2e édition avec une version périmée (1) → 409 conflit.
        $stale = $client->request('PATCH', '/api/children/'.$childId, [
            'json' => ['schoolLabel' => 'CM2', 'expectedVersion' => 1],
            'headers' => $scoped + ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(409, $stale->getStatusCode());
        self::assertSame('/errors/conflict', $stale->toArray(false)['type']);
    }

    public function testNotesSurviveAuthorRemoval(): void
    {
        // P0 (revue) : retirer un membre auteur d'une note ne doit PAS détruire la note (§4.8).
        [$client, $auth, $familyId] = $this->adminWithFamily('c-survive@example.com');
        $childId = $this->createChild($client, $auth, $familyId);
        $this->addMember($familyId, 'c-author@example.com', MembershipRole::StepParent, RightLevel::Edit);

        $spClient = self::createClient();
        $spClient->disableReboot();
        $spScoped = $this->bearer($this->login($spClient, 'c-author@example.com')) + ['X-Family-Id' => $familyId];
        $spClient->request('POST', '/api/children/'.$childId.'/notes', [
            'json' => ['text' => 'note qui doit survivre'],
            'headers' => $spScoped,
        ]);

        // L'admin retire le beau-parent auteur.
        $members = $client->request('GET', '/api/families/'.$familyId.'/members', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ])->toArray();
        $authorMembership = array_values(array_filter($members, static fn ($m) => 'c-author@example.com' === $m['email']))[0]['id'];
        $delete = $client->request('DELETE', '/api/memberships/'.$authorMembership, [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(204, $delete->getStatusCode());

        // La note survit, auteur anonymisé.
        $notes = $client->request('GET', '/api/children/'.$childId.'/notes', [
            'headers' => $auth + ['X-Family-Id' => $familyId],
        ]);
        self::assertSame(200, $notes->getStatusCode());
        $body = $notes->toArray();
        self::assertCount(1, $body, 'la note doit survivre au retrait de son auteur');
        self::assertSame('note qui doit survivre', $body[0]['text']);
        self::assertSame('Membre supprimé', $body[0]['authorName']);
    }

    public function testAccessBlockListsMembersByLevel(): void
    {
        [$client, $auth, $familyId] = $this->adminWithFamily('c-admin6@example.com');
        $childId = $this->createChild($client, $auth, $familyId);
        $this->addMember($familyId, 'c-view6@example.com', MembershipRole::StepParent, RightLevel::View);
        $this->addMember($familyId, 'c-none6@example.com', MembershipRole::StepParent, RightLevel::None);

        $access = $client->request('GET', '/api/children/'.$childId.'/access', ['headers' => $auth + ['X-Family-Id' => $familyId]]);
        self::assertSame(200, $access->getStatusCode());
        $entries = $access->toArray();
        // L'admin (edit) + le beau-parent view = 2 ; le membre none n'est pas listé.
        self::assertCount(2, $entries);
        self::assertSame('edit', $entries[0]['level']);
    }
}
