<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Child;
use App\Entity\CustodyDay;
use App\Entity\CustodyPattern;
use App\Entity\Exchange;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\CustodyDaySource;
use App\Enum\CustodyPatternKind;
use App\Enum\ExchangeStatus;
use App\Enum\ExchangeType;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Repository\ExchangeReadRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Liste « Reste à traiter » de `specs/revue-web-desktop-2026-07.md` — finitions P2/P3.
 *
 * Elles ont un point commun : chacune fait **dire à l'écran quelque chose de faux ou de vide** —
 * un libellé que rien n'associe à son champ, un badge qui emprunte la couleur d'un parent, un
 * marqueur sans légende, un volet qui demande de sélectionner ce qui n'existe pas.
 */
final class WebFinishingTouchesTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    // ───────────────────────── Accessibilité ─────────────────────────

    /** Lien d'évitement (WCAG 2.4.1) : la barre latérale porte huit liens, retraversés à chaque page. */
    public function testSkipLinkTargetsTheMainContent(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $skip = $crawler->filter('a.skip-link');
        self::assertSame(1, $skip->count());
        self::assertSame('#main', $skip->attr('href'));
        self::assertSame(1, $crawler->filter('main#main')->count());
    }

    /** Le sélecteur de famille annonce son état et sa nature (il n'annonçait rien). */
    public function testFamilySwitcherAnnouncesItsState(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $crawler = $client->request('GET', '/');
        $button = $crawler->filter('.family-select__button');
        self::assertSame('false', $button->attr('aria-expanded'));
        self::assertSame('true', $button->attr('aria-haspopup'));
        self::assertSame('menu', $crawler->filter('.family-select__menu')->attr('role'));
        // `hidden` plutôt qu'une classe : l'état est le même pour l'œil et pour l'assistance.
        self::assertNotNull($crawler->filter('.family-select__menu')->attr('hidden'));
    }

    /**
     * Panneau d'édition d'un événement : chaque libellé était posé avant son champ, sans `for` et
     * sans l'envelopper — rien ne les associait (WCAG 1.3.1/4.1.2).
     */
    public function testEventEditionLabelsAreBoundToTheirField(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->seedEvent();
        $this->login($client);

        $crawler = $client->request('GET', '/agenda');
        self::assertResponseIsSuccessful();

        $panel = $crawler->filter('form.agenda-edit')->first();
        self::assertGreaterThan(0, $panel->count(), 'Aucun panneau d\'édition rendu.');
        $labels = $panel->filter('label')->each(
            static fn ($node): array => ['for' => (string) $node->attr('for'), 'text' => trim($node->text())],
        );
        self::assertNotEmpty($labels);
        foreach ($labels as $label) {
            self::assertNotSame('', $label['for'], 'Un libellé du panneau d\'édition n\'est lié à aucun champ.');
            self::assertSame(
                1,
                $crawler->filter('#'.$label['for'])->count(),
                \sprintf('Le libellé « %s » désigne un champ inexistant (%s).', $label['text'], $label['for']),
            );
        }
    }

    // ───────────────────────── Signaux trompeurs ─────────────────────────

    /**
     * Le marqueur « famille liée » était un point plein `#9C6FB0` — la teinte que le §8 donne AUSSI
     * à la catégorie « Musique ». Le §8 fixant les deux couleurs, on distingue par la forme.
     */
    public function testCalendarLegendExplainsItsMarkers(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily();
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // « vac. » se répétait sur les cases sans qu'aucune légende ne le nomme.
        self::assertStringContainsString('garde-legend__tag', $html);
        self::assertStringContainsString('Vacances scolaires', $html);
    }

    /** Les jours sans couleur sont expliqués — le mois semblait à moitié cassé. */
    public function testCalendarExplainsUnattributedDays(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily();
        $this->login($client);

        $client->request('GET', '/garde');
        $html = (string) $client->getResponse()->getContent();
        // Un seul jour est matérialisé : tous les autres du mois sont sans couleur.
        self::assertStringContainsString('Les jours sans couleur ne sont pas attribués', $html);
    }

    /** Une fois le rythme configuré, l'écran mène enfin à son éditeur. */
    public function testGardeLinksToTheRhythmEditorOnceConfigured(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedCustodyFamily();
        $this->login($client);

        $crawler = $client->request('GET', '/garde');
        $edit = $crawler->filter('.garde-cal__edit');
        self::assertSame(1, $edit->count());
        self::assertStringContainsString('tab=garde', (string) $edit->attr('href'));
    }

    // ───────────────────────── Cohérence visuelle ─────────────────────────

    /**
     * Le déclencheur de création avait trois apparences pour un même geste. Il n'en a plus qu'une,
     * partagée par l'agenda, les échanges et les enfants.
     */
    public function testCreationTriggerLooksTheSameEverywhere(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        foreach (['/agenda', '/echanges', '/children'] as $screen) {
            $crawler = $client->request('GET', $screen);
            self::assertResponseIsSuccessful();
            self::assertSame(
                1,
                $crawler->filter('details.create-panel > summary.create-panel__cta')->count(),
                \sprintf('%s : déclencheur de création non conforme.', $screen),
            );
        }
    }

    /** Une seule couleur d'action : `btn--ink` a été fondu dans `btn--primary`. */
    public function testNoScreenKeepsTheOldSecondPrimaryButton(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        foreach (['/', '/agenda', '/echanges', '/children', '/settings', '/settings?tab=famille'] as $screen) {
            $client->request('GET', $screen);
            self::assertStringNotContainsString('btn--ink', (string) $client->getResponse()->getContent(), $screen);
        }
    }

    // ───────────────────────── Parcours et états vides ─────────────────────────

    /** `/echanges` ouvrait « Sélectionnez un échange » alors qu'il y en avait à montrer. */
    public function testExchangesPreselectTheFirstOne(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->seedExchange();
        $this->login($client);

        $client->request('GET', '/echanges');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Sélectionnez un échange', $html);
        self::assertStringContainsString('Vacances de février', $html);
    }

    /** Famille neuve : le volet ne demande plus de sélectionner ce qui n'existe pas. */
    public function testEmptyPanesDoNotAskToSelectAnAbsentItem(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $this->login($client);

        $client->request('GET', '/children');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Sélectionnez un enfant', $html);
        self::assertStringContainsString('Aucun enfant enregistré', $html);

        $client->request('GET', '/echanges');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Sélectionnez un échange', $html);
        self::assertStringContainsString('Aucun échange pour le moment', $html);
    }

    // ───────────────────────── E1 — association parent ↔ beau-parent ─────────────────────────

    /**
     * L'écran Membres offre le sélecteur de conjoint et affiche la mention des deux côtés
     * (évolution E1, `specs/evolutions-2026-09.md`).
     */
    public function testMembersScreenExposesThePartnerAssociation(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $step = $this->addStepParent();
        $this->login($client);

        $crawler = $client->request('GET', '/settings?tab=famille');
        self::assertResponseIsSuccessful();

        // Le sélecteur ne propose que le rôle opposé : un couple relie un parent et un beau-parent.
        $select = $crawler->filter('form[data-membership-id="'.$step->getId().'"] select[name="partnerMembershipId"]');
        self::assertSame(1, $select->count());
        $options = $select->filter('option')->each(static fn ($o): string => trim($o->text()));
        self::assertContains('Alice Web', $options);

        // Une fois l'association posée, la mention se lit sur les DEUX lignes.
        // Les entités chargées AVANT une requête HTTP sont détachées par la réinitialisation des
        // services du client de test : les modifier ne produirait aucun UPDATE. On les relit.
        $step = $this->em()->find(Membership::class, $step->getId());
        self::assertInstanceOf(Membership::class, $step);
        $parent = $this->em()->getRepository(Membership::class)
            ->findOneBy(['family' => $this->family(), 'role' => MembershipRole::Parent]);
        self::assertInstanceOf(Membership::class, $parent);
        $step->setPartnerMembership($parent);
        $this->em()->flush();

        $client->request('GET', '/settings?tab=famille');
        $html = (string) $client->getResponse()->getContent();
        self::assertSame(2, substr_count($html, 'Conjoint·e de'));
    }

    // ───────────────────────── Échanges : fil et carte (X3/X4) ─────────────────────────

    /**
     * Le fil posait tous les messages à gauche : deux parents qui s'écrivent lisaient un journal,
     * pas un dialogue (revue UX des échanges, X3). Et la carte de liste écrivait « Un enfant »
     * alors qu'elle avait l'identifiant sans le nom (X4).
     */
    public function testExchangeThreadAndCardFollowTheMockup(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $exchange = $this->seedExchangeWithMessages();
        $this->login($client);

        $crawler = $client->request('GET', '/echanges?exchange='.$exchange->getId());
        self::assertResponseIsSuccessful();

        // Le message du membre courant porte le modificateur, celui de l'autre non.
        self::assertSame(1, $crawler->filter('.exchange-message--mine')->count());
        self::assertSame(2, $crawler->filter('.exchange-message')->count());
        // Pas d'avatar sur sa propre bulle : on sait qui l'on est.
        self::assertSame(0, $crawler->filter('.exchange-message--mine .avatar')->count());
        self::assertSame(1, $crawler->filter('.exchange-message:not(.exchange-message--mine) .avatar')->count());

        // La carte nomme l'enfant et l'auteur.
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Léa', $html);
        self::assertStringNotContainsString('Un enfant', $html);
        self::assertSame(1, $crawler->filter('.exchange-card__child')->count());
    }

    /**
     * X5 — un fil qui vient de bouger doit se distinguer d'un fil dormant, sans qu'il faille
     * l'ouvrir. Le marqueur ne tient pas qu'à la couleur (§8.1) : pastille, graisse et texte.
     */
    public function testUnreadExchangesAreMarkedInTheList(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $exchange = $this->seedExchangeWithMessages();
        $this->login($client);

        // Jamais lu par le membre courant : la carte porte la pastille et l'annonce en toutes lettres.
        $crawler = $client->request('GET', '/echanges');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('.exchange-card--unread')->count());
        self::assertSame(1, $crawler->filter('.exchange-card__unread')->count());
        self::assertStringContainsString('Non lu', (string) $client->getResponse()->getContent());
        // C'est le navigateur qui marquera lu, par POST : le rendu ne consomme rien, sinon un simple
        // préchargement au survol ferait tomber la pastille.
        self::assertStringContainsString(
            'data-exchanges-unread-id-value="'.$exchange->getId().'"',
            (string) $client->getResponse()->getContent(),
        );

        // Une fois le fil marqué lu, plus rien ne le signale.
        $this->markRead($exchange);
        $crawler = $client->request('GET', '/echanges');
        self::assertSame(0, $crawler->filter('.exchange-card--unread')->count());
        self::assertSame(0, $crawler->filter('.exchange-card__unread')->count());
    }

    /**
     * X6 — l'échéance se corrige depuis l'écran (point 7), et le formulaire de création ne demande
     * plus tout d'emblée (point 8).
     */
    public function testDeadlineIsEditableAndCreationFormIsProgressive(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser();
        $exchange = $this->seedExchangeWithMessages();
        $this->login($client);

        $crawler = $client->request('GET', '/echanges?exchange='.$exchange->getId());
        self::assertResponseIsSuccessful();

        // Le serveur acceptait `deadline` depuis toujours ; aucune UI ne l'offrait.
        self::assertSame(1, $crawler->filter('form.exchange-deadline input[type="date"]')->count());
        self::assertStringContainsString(
            'exchanges#updateDeadline',
            (string) $client->getResponse()->getContent(),
        );

        // Les champs secondaires sont repliés : poser une question simple est l'action la plus
        // fréquente, et elle ne demande plus que type, titre et message.
        self::assertSame(1, $crawler->filter('details.exchange-form__more')->count());
        self::assertSame(0, $crawler->filter('details.exchange-form__more[open]')->count());
        self::assertSame(1, $crawler->filter('details.exchange-form__more #e-child')->count());
        self::assertSame(1, $crawler->filter('details.exchange-form__more #e-deadline')->count());
    }

    private function markRead(Exchange $exchange): void
    {
        $family = $this->family();
        $me = $this->em()->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $me);
        $reads = self::getContainer()->get(ExchangeReadRepository::class);
        self::assertInstanceOf(ExchangeReadRepository::class, $reads);
        $messages = self::getContainer()->get(MessageRepository::class);
        self::assertInstanceOf(MessageRepository::class, $messages);
        $fresh = $this->em()->find(Exchange::class, $exchange->getId());
        self::assertInstanceOf(Exchange::class, $fresh);
        $reads->markRead($me, $fresh, Clock::get()->now(), $messages->lastFor($fresh));
        $this->em()->flush();
    }

    // ───────────────────────── fixtures ─────────────────────────

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    /** Beau-parent de la famille, éligible à une association de couple. */
    private function addStepParent(): Membership
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail('step@example.com')->setFullName('Bob Beau')
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $membership = (new Membership())->setUser($user)->setFamily($this->family())
            ->setRole(MembershipRole::StepParent)->setStatus(MembershipStatus::Active);
        $this->em()->persist($user);
        $this->em()->persist($membership);
        $this->em()->flush();

        return $membership;
    }

    private function createUser(): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail('web@example.com')->setFullName('Alice Web')
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $family = (new Family())->setName('Famille Web')->setType(FamilyType::Separated);
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
        $this->em()->persist($user);
        $this->em()->persist($family);
        $this->em()->persist($membership);
        $this->em()->flush();

        return $user;
    }

    private function family(): Family
    {
        $family = $this->em()->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);

        return $family;
    }

    private function seedEvent(): void
    {
        $family = $this->family();
        $child = (new Child())->setFamily($family)->setFirstName('Léa');
        $this->em()->persist($child);
        $event = (new \App\Entity\Event())->setFamily($family)->setTitle('Judo')
            ->setCategory(\App\Enum\EventCategory::Sport)->setWholeFamily(true)
            ->setStartsAt(Clock::get()->now()->setTime(18, 0));
        $this->em()->persist($event);
        $this->em()->flush();
    }

    /** Échange concernant un enfant nommé, avec un message de chaque parent. */
    private function seedExchangeWithMessages(): Exchange
    {
        $family = $this->family();
        $me = $this->em()->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $me);
        $other = $this->addStepParent();

        $child = (new Child())->setFamily($family)->setFirstName('Léa');
        $exchange = (new Exchange())->setFamily($family)->setAuthorMembership($me)
            ->setType(ExchangeType::Discussion)->setStatus(ExchangeStatus::Open)
            ->setTitle('Inscription piscine')->setChild($child);
        $this->em()->persist($child);
        $this->em()->persist($exchange);
        $this->em()->persist(
            (new Message())->setExchange($exchange)->setAuthorMembership($other)
                ->setText('On inscrit Léa ?')->setSentAt(Clock::get()->now()->modify('-1 hour')),
        );
        $this->em()->persist(
            (new Message())->setExchange($exchange)->setAuthorMembership($me)
                ->setText('Oui, je m\'en occupe.')->setSentAt(Clock::get()->now()),
        );
        $this->em()->flush();

        return $exchange;
    }

    private function seedExchange(): void
    {
        $family = $this->family();
        $author = $this->em()->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $author);
        $exchange = (new Exchange())->setFamily($family)->setAuthorMembership($author)
            ->setType(ExchangeType::Discussion)->setStatus(ExchangeStatus::Open)
            ->setTitle('Vacances de février');
        $this->em()->persist($exchange);
        $this->em()->flush();
    }

    private function seedCustodyFamily(): void
    {
        $this->createUser();
        $em = $this->em();
        $family = $this->family();
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
        $em->persist((new CustodyPattern())->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)->setAnchorDate($today)
            ->setHandoverWeekday(0)->setHandoverTime('19:00')
            ->setParentA($viewer)->setParentB($parentB));
        $em->persist((new CustodyDay())->setFamily($family)->setDate($today)
            ->setParentMembership($viewer)->setSource(CustodyDaySource::Base));
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
