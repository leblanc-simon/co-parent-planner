<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Event;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Entity\Membership;
use App\Entity\OverlayPreference;
use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\FamilyLinkStatus;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\OverlayView;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Front web (cdc §4.3) : rendu des écrans d'auth, onboarding obligatoire, accueil authentifié
 * via le cookie JWT HttpOnly (décision D2).
 */
final class WebPagesTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    private function createUser(KernelBrowser $client, bool $withFamily): void
    {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail('web@example.com')
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);

        if ($withFamily) {
            $family = (new Family())->setName('Famille Web')->setType(FamilyType::Separated);
            $membership = (new Membership())->setUser($user)->setFamily($family)
                ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
            $em->persist($family);
            $em->persist($membership);
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

    public function testPublicAuthPagesRender(): void
    {
        $client = static::createClient();

        foreach ([
            '/login' => 'Connexion',
            '/register' => 'Créer un compte',
            '/forgot-password' => 'Mot de passe oublié',
            '/reset-password?token=abc' => 'Nouveau mot de passe',
            '/verify-email?token=abc' => 'Vérification',
        ] as $url => $text) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString($text, (string) $client->getResponse()->getContent());
        }
    }

    public function testHomeRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testHomeRedirectsToOnboardingWhenNoFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: false);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseRedirects('/onboarding');
    }

    public function testAuthenticatedHomeRenders(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Bonjour', (string) $client->getResponse()->getContent());
        self::assertStringContainsString('Famille Web', (string) $client->getResponse()->getContent());
    }

    public function testSettingsPageRendersForAdmin(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Compte', $html);
        self::assertStringContainsString('Réglages de la famille', $html);
        self::assertStringContainsString('Inviter une personne', $html);
        self::assertStringContainsString('Familles liées', $html);
    }

    public function testSettingsRedirectsToOnboardingWhenNoFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: false);
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseRedirects('/onboarding');
    }

    public function testChildrenPageRenders(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/children');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Enfants', (string) $client->getResponse()->getContent());
    }

    public function testAgendaPageRendersEmpty(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/agenda');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Agenda', $html);
        self::assertStringContainsString('agenda-create__cta', $html); // parent admin → agenda:edit (CTA compact « Ajouter »)
        self::assertStringContainsString('Aucun événement ce mois-ci', $html);
        self::assertStringContainsString('turbo-stream-source', $html);
        self::assertStringContainsString('href="/agenda"', $html); // onglet dans la barre latérale
    }

    public function testAgendaListsEventAndHomeShowsUpcoming(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);
        $membership = $em->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $membership);

        // À venir (relatif à « maintenant » réel — pas de mockTime pour ne pas périmer le cookie JWT).
        $start = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->modify('+2 days')->setTime(15, 30);
        $event = (new Event())
            ->setFamily($family)
            ->setTitle('Cours de tennis')
            ->setCategory(EventCategory::Sport)
            ->setResponsibleMembership($membership)
            ->setWholeFamily(true)
            ->setStartsAt($start->setTimezone(new \DateTimeZone('UTC')));
        $em->persist($event);
        $em->flush();

        // Écran Agenda : l'événement figure sous son jour.
        $client->request('GET', '/agenda?month='.$start->format('Y-m'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Cours de tennis', (string) $client->getResponse()->getContent());

        // Accueil : bloc « À venir » (US-AG-7).
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Cours de tennis', (string) $client->getResponse()->getContent());
    }

    public function testAgendaShowsLinkedFamilyOverlayWhenEnabled(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $familyA = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $familyA);
        $membershipA = $em->getRepository(Membership::class)->findOneBy(['family' => $familyA]);
        self::assertInstanceOf(Membership::class, $membershipA);

        // Famille liée B + événement futur + lien actif + préférence overlay agenda activée.
        $start = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->modify('+2 days')->setTime(15, 30);
        $familyB = (new Family())->setName('Foyer lié')->setType(FamilyType::Separated);
        $userB = (new User())->setEmail('web-b@example.com')->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $userB->setPasswordHash($hasher->hashPassword($userB, self::PASSWORD));
        $membershipB = (new Membership())->setUser($userB)->setFamily($familyB)->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $eventB = (new Event())->setFamily($familyB)->setTitle('Judo du second foyer')->setCategory(EventCategory::Sport)
            ->setResponsibleMembership($membershipB)->setWholeFamily(true)->setStartsAt($start->setTimezone(new \DateTimeZone('UTC')));
        $link = (new FamilyLink($familyA, $familyB, null))->setStatus(FamilyLinkStatus::Active);
        $pref = (new OverlayPreference())->setMembership($membershipA)->setFamilyLink($link)->setView(OverlayView::Agenda)->setEnabled(true);
        foreach ([$familyB, $userB, $membershipB, $eventB, $link, $pref] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->request('GET', '/agenda?month='.$start->format('Y-m'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Judo du second foyer', $html); // événement lié fusionné
        self::assertStringContainsString('Famille liée', $html); // badge overlay
        self::assertStringContainsString('Événements de la famille liée', $html); // carte toggle overlay (WAG-05)
    }

    public function testGardePageRendersForSeparatedFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true); // FamilyType::Separated → garde active
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Sans rythme configuré : message + builder de configuration (parent admin → canEdit).
        self::assertStringContainsString('Aucun rythme de garde configuré', $html);
        self::assertStringContainsString('Configurer le rythme', $html);
        // L'onglet Garde figure dans la barre latérale d'une famille séparée.
        self::assertStringContainsString('href="/garde"', $html);
        // Abonnement temps réel câblé.
        self::assertStringContainsString('turbo-stream-source', $html);
    }
}
