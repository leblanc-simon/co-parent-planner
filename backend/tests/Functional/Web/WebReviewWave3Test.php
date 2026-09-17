<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\CustodyDay;
use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\CustodyDaySource;
use App\Enum\CustodyPatternKind;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Service\Custody\DaySegment;
use App\Service\Custody\DaySegments;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Vague 3 de l'étape 6 de `specs/revue-web-desktop-2026-07.md` — **écarts de périmètre** :
 *
 * - **6.5** l'accueil n'énonçait ni chez qui sont les enfants, ni quand ils changent de foyer ;
 * - **6.4** `/settings` empilait ses neuf sections sur une page unique, sans jamais marquer où
 *   l'on se trouve, là où la maquette impose trois onglets exclusifs ;
 * - **6.3** aucun écran RGPD ni gestion de compte, alors que l'API porte les trois opérations
 *   depuis le Lot 8 et que le mobile les expose.
 */
final class WebReviewWave3Test extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    // ───────────────────────── 6.5 — la phrase de garde de l'accueil ─────────────────────────

    /** Semaine du membre connecté : la carte le dit, et annonce la transmission suivante. */
    public function testHomeStatesWhereTheChildrenAreAndWhenTheyMove(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily(todayBelongsToViewer: true);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Les enfants sont avec vous cette semaine.', $html);
        // Le liseré suit la garde DU JOUR : constant, il désignait le parent A une semaine sur deux
        // à celui qui avait pourtant les enfants.
        self::assertStringContainsString('home-hero--a', $html);
        // « Le prochain échange » du constat : la transmission, lue sur les jours matérialisés.
        self::assertStringContainsString('Retour chez', $html);
        self::assertStringContainsString('19:00', $html);
    }

    /** Semaine de l'autre parent : la phrase le nomme, et le liseré prend sa couleur. */
    public function testHomeNamesTheOtherParentOnTheirWeek(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily(todayBelongsToViewer: false);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Les enfants sont chez', $html);
        self::assertStringContainsString('home-hero--b', $html);
        // De retour chez le membre connecté : la phrase s'adresse à lui, elle ne le nomme pas.
        self::assertStringContainsString('De retour chez vous', $html);
    }

    /**
     * Jour non matérialisé (hors horizon généré) : ni phrase, ni couleur de parent. Annoncer une
     * garde qu'on ne connaît pas serait pire que de se taire.
     */
    public function testHomeStaysNeutralWithoutAMaterialisedDay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily(todayBelongsToViewer: true, materialiseToday: false);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('home-hero--none', $html);
        self::assertStringNotContainsString('Les enfants sont', $html);
    }

    // ───────────────────────── 6.4 — les trois onglets de /settings ─────────────────────────

    public function testSettingsTabsAreExclusiveAndMarkTheCurrentOne(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily(todayBelongsToViewer: true);
        $this->login($client);

        // Onglet par défaut : le compte, et lui seul.
        $crawler = $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('#sec-account')->count());
        self::assertSame(0, $crawler->filter('#sec-members')->count());
        self::assertSame(0, $crawler->filter('#sec-custody')->count());
        // La navigation marque enfin la position courante — neuf ancres n'en marquaient aucune.
        $active = $crawler->filter('.settings-nav a[aria-current="page"]');
        self::assertSame(1, $active->count());
        self::assertSame('Mon compte', trim($active->text()));

        $crawler = $client->request('GET', '/settings?tab=famille');
        self::assertSame(1, $crawler->filter('#sec-members')->count());
        self::assertSame(0, $crawler->filter('#sec-account')->count());
        self::assertSame('Famille', trim($crawler->filter('.settings-nav a[aria-current="page"]')->text()));

        $crawler = $client->request('GET', '/settings?tab=garde');
        self::assertSame(1, $crawler->filter('#sec-custody')->count());
        self::assertSame(0, $crawler->filter('#sec-members')->count());
    }

    /** Onglet inconnu ou forgé : repli sur le compte plutôt qu'une page vide. */
    public function testUnknownTabFallsBackToAccount(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily(todayBelongsToViewer: true);
        $this->login($client);

        $crawler = $client->request('GET', '/settings?tab=nimporte-quoi');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('#sec-account')->count());
    }

    /** Famille unie : l'onglet « Planning de garde » n'existe pas, et n'est pas atteignable. */
    public function testCustodyTabIsAbsentForAnIntactFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(intact: true);
        $this->login($client);

        $crawler = $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Planning de garde', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/settings?tab=garde');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('#sec-account')->count());
        self::assertSame(0, $crawler->filter('#sec-custody')->count());
    }

    // ───────────────────────── 6.3 — compte modifiable et écran RGPD ─────────────────────────

    public function testAccountTabExposesProfileEditionAndRgpdActions(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $crawler = $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();

        // Profil modifiable (PATCH /api/me) : « Mon compte » n'était qu'un affichage.
        self::assertSame(1, $crawler->filter('form[data-settings-target="profileForm"] #p-name')->count());
        self::assertSame(1, $crawler->filter('#p-phone')->count());
        self::assertSame(1, $crawler->filter('[data-action="settings#requestPasswordReset"]')->count());
        // L'e-mail du compte est passé au contrôleur : le lien de changement part vers l'adresse
        // vérifiée, jamais vers une adresse saisie sur cette page.
        self::assertSame(
            'web@example.com',
            $crawler->filter('[data-controller="settings"]')->attr('data-settings-user-email'),
        );

        // Écran RGPD (US-SET-4 / US-SET-4b) : il n'existait que sur mobile.
        self::assertSame(1, $crawler->filter('#sec-privacy')->count());
        self::assertSame(1, $crawler->filter('[data-action="settings#requestExport"]')->count());
        self::assertSame(1, $crawler->filter('[data-action="settings#deleteAccount"]')->count());
    }

    /** Les trois opérations que l'écran pilote existent bien et répondent au compte courant. */
    public function testRgpdOperationsAnswerTheWebClient(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $client->request('PATCH', '/api/me', server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['fullName' => 'Alice Modifiée', 'phone' => '0102030405'], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/me/export', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: '{}');
        self::assertResponseStatusCodeSame(202);
        $operation = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($operation);
        self::assertArrayHasKey('id', $operation);

        // Le statut se relit par l'identifiant — que l'écran conserve, l'API n'exposant aucune
        // liste des opérations du sujet (même piège que le constat mobile C52).
        $client->request('GET', '/api/me/export/'.$operation['id'], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        // Le profil modifié est bien relu par l'écran.
        $client->request('GET', '/settings');
        self::assertStringContainsString('Alice Modifiée', (string) $client->getResponse()->getContent());
    }

    // ───────────────────────── fixtures ─────────────────────────

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function createUser(bool $intact = false): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail('web@example.com')
            ->setFullName('Alice Web')
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);

        $family = (new Family())->setName('Famille Web')
            ->setType($intact ? FamilyType::Intact : FamilyType::Separated);
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
        $this->em()->persist($family);
        $this->em()->persist($membership);
        $this->em()->flush();

        return $user;
    }

    /**
     * Famille séparée à deux parents, rythme ancré, jour du jour matérialisé chez l'un ou l'autre,
     * et un jour attribué à l'autre parent trois jours plus tard — la transmission à annoncer.
     */
    private function seedCustodyFamily(bool $todayBelongsToViewer, bool $materialiseToday = true): void
    {
        $this->createUser();
        $em = $this->em();
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);
        $viewer = $em->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $viewer);

        $other = (new User())->setEmail('web-b@example.com')->setFullName('Marc Autre')
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $other->setPasswordHash('x');
        $parentB = (new Membership())->setUser($other)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);
        $em->persist($other);
        $em->persist($parentB);

        $today = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $pattern = (new CustodyPattern())->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate($today)->setHandoverWeekday(0)->setHandoverTime('19:00')
            ->setParentA($viewer)->setParentB($parentB);
        $em->persist($pattern);

        if ($materialiseToday) {
            $holder = $todayBelongsToViewer ? $viewer : $parentB;
            $next = $todayBelongsToViewer ? $parentB : $viewer;

            $em->persist((new CustodyDay())->setFamily($family)->setDate($today)
                ->setParentMembership($holder)
                ->setSource(CustodyDaySource::Base));
            // Dernier jour du bloc, deux jours plus tard : c'est LUI qui porte la transmission
            // (jusqu'à 19:00 chez l'attributaire, puis chez l'autre — cdc §3.4), et c'est elle que
            // l'accueil annonce.
            $handoverDay = DaySegments::nominal(
                (string) $holder->getId(),
                (string) $next->getId(),
                DaySegment::minutesOf('19:00'),
                CustodyDaySource::Base,
                CustodyDaySource::Base,
            );
            $em->persist((new CustodyDay())->setFamily($family)->setDate($today->modify('+2 days'))
                ->setParentMembership($holder)
                ->setSource(CustodyDaySource::Base)
                ->setSegments($handoverDay->toArray()));
            $em->persist((new CustodyDay())->setFamily($family)->setDate($today->modify('+3 days'))
                ->setParentMembership($next)
                ->setSource(CustodyDaySource::Base));
        }

        $em->flush();
    }

    private function login(KernelBrowser $client): void
    {
        $client->request('POST', '/api/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['email' => 'web@example.com', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
