<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Event;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\OverlayPreference;
use App\Entity\User;
use App\Enum\EventCategory;
use App\Enum\FamilyLinkStatus;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\OverlayView;
use App\Enum\UserStatus;
use App\Security\Auth\TokenHasher;
use App\Security\Encryption\BlindIndexHasher;
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

    private function seedInvitation(
        string $email,
        string $token,
        string $familyName = 'Famille Invitante',
        InvitationStatus $status = InvitationStatus::Pending,
        int $expiresInDays = 7,
    ): void {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(TokenHasher::class);
        $blind = $container->get(BlindIndexHasher::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(TokenHasher::class, $hasher);
        self::assertInstanceOf(BlindIndexHasher::class, $blind);

        $founder = (new User())->setEmail('founder-'.$email)->setStatus(UserStatus::Active);
        $founder->setPasswordHash('x');
        $family = (new Family())->setName($familyName)->setType(FamilyType::Separated);
        $founderMembership = (new Membership())->setUser($founder)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail($email)
            ->setEmailHash($blind->hash($email))
            ->setRole(MembershipRole::Parent)
            ->setProposedRights([])
            ->setProposedAdmin(false)
            ->setTokenHash($hasher->hash($token))
            ->setExpiresAt(Clock::get()->now()->modify(($expiresInDays >= 0 ? '+' : '-').abs($expiresInDays).' days'))
            ->setStatus($status);

        foreach ([$founder, $family, $founderMembership, $invitation] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
    }

    public function testAcceptInvitationShowsJoinFormWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedInvitation('invitee@example.com', 'plain-token-123');

        // Anonyme + aucun compte pour l'invité·e → écran unique « créer le compte ET rejoindre ».
        $client->request('GET', '/invitations/accept?token=plain-token-123');
        self::assertResponseIsSuccessful();
        // Le token transite en query du GET → pas de fuite via Referer.
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Rejoindre Famille Invitante', $html);
        self::assertStringContainsString('/api/auth/register-and-accept-invitation', $html); // création + acceptation en un POST
        self::assertStringContainsString('value="invitee@example.com"', $html);              // e-mail nominatif pré-rempli
        self::assertStringContainsString('value="plain-token-123"', $html);                  // token en champ caché (plus en session)
    }

    public function testAcceptInvitationShowsInvalidStateForBadToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invitations/accept?token=inconnu');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invitation indisponible', (string) $client->getResponse()->getContent());
    }

    public function testAcceptInvitationDoesNotLeakFamilyOnWrongToken(): void
    {
        // Non-énumération (invariant CLAUDE.md) : un token erroné ne doit révéler ni la famille ni
        // l'e-mail d'une invitation pourtant bien réelle.
        $client = static::createClient();
        $client->disableReboot();
        $this->seedInvitation('victime@example.com', 'vrai-token-secret', familyName: 'Famille Secrete');

        $client->request('GET', '/invitations/accept?token=mauvais-token');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Invitation indisponible', $html);
        self::assertStringNotContainsString('Famille Secrete', $html);
        self::assertStringNotContainsString('victime@example.com', $html);
    }

    /**
     * @return iterable<string, array{InvitationStatus, int}>
     */
    public static function invalidInvitationProvider(): iterable
    {
        yield 'expirée (pending mais passée)' => [InvitationStatus::Pending, -1];
        yield 'déjà acceptée' => [InvitationStatus::Accepted, 7];
        yield 'révoquée' => [InvitationStatus::Revoked, 7];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInvitationProvider')]
    public function testAcceptInvitationShowsInvalidStateForNonUsableInvitation(InvitationStatus $status, int $expiresInDays): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedInvitation('x@example.com', 'token-nonutilisable', familyName: 'Famille Cachée', status: $status, expiresInDays: $expiresInDays);

        $client->request('GET', '/invitations/accept?token=token-nonutilisable');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Invitation indisponible', $html);
        self::assertStringNotContainsString('Famille Cachée', $html);
    }

    public function testAcceptInvitationShowsLoginToJoinWhenAccountExists(): void
    {
        // Anonyme + un compte existe DÉJÀ pour l'adresse invitée → écran « se connecter et rejoindre »
        // (mot de passe exigé, frontière de sécurité). Pas de création de compte silencieuse.
        $client = static::createClient();
        $client->disableReboot();
        $this->seedInvitation('web@example.com', 'plain-token-456', familyName: 'Famille Existante');
        $this->createUser($client, withFamily: false); // compte actif pour web@example.com

        $client->request('GET', '/invitations/accept?token=plain-token-456');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Rejoindre Famille Existante', $html);
        self::assertStringContainsString('/api/auth/login-and-accept-invitation', $html);
        self::assertStringContainsString('value="plain-token-456"', $html);
        self::assertStringContainsString('value="web@example.com"', $html);
    }

    public function testLoginHasNoInvitationRedirectByDefault(): void
    {
        // Négatif de testLoginRedirectsToAcceptAfterPendingInvitation : sans invitation en session,
        // l'après-connexion reste l'accueil et aucune bannière n'apparaît.
        $client = static::createClient();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-auth-form-redirect-value="/"', $html);
        self::assertStringNotContainsString('rejoindre la famille qui vous a invité', $html);
    }

    public function testInvalidTokenIsNotMemorisedForLoginRedirect(): void
    {
        // Constat P1 : un token invalide ne doit pas réarmer la redirection post-connexion.
        $client = static::createClient();
        $client->disableReboot();

        $client->request('GET', '/invitations/accept?token=token-bidon');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/login');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('data-auth-form-redirect-value="/"', $html);
        self::assertStringNotContainsString('rejoindre la famille qui vous a invité', $html);
    }

    public function testAcceptInvitationRendersConfirmWhenAuthenticated(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);
        $this->seedInvitation('web@example.com', 'confirm-token', familyName: 'Famille Confirm');

        $client->request('GET', '/invitations/accept?token=confirm-token');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Rejoindre Famille Confirm', $html); // confirmation explicite
        self::assertStringContainsString('action="/api/invitations/accept"', $html);
        self::assertStringContainsString('value="confirm-token"', $html);
    }

    public function testAcceptInvitationShowsMismatchWhenAuthenticatedAsOtherUser(): void
    {
        // Connecté·e sous un e-mail différent de l'invité·e (invitation nominative) → écran d'impasse
        // « se déconnecter et continuer », avec réarmement du token vers /invitations/accept.
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true); // web@example.com
        $this->login($client);
        $this->seedInvitation('someone-else@example.com', 'mismatch-token', familyName: 'Famille Autre');

        $client->request('GET', '/invitations/accept?token=mismatch-token');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Se déconnecter et continuer', $html);
        self::assertStringContainsString('someone-else@example.com', $html);
        self::assertStringContainsString('web@example.com', $html);
        // La cible de reprise post-déconnexion pointe vers l'acceptation avec le token.
        self::assertStringContainsString('/invitations/accept?token=mismatch-token', $html);
    }

    public function testAcceptInvitationShowsInvalidStateWhenAuthenticatedWithoutToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/invitations/accept');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invitation indisponible', (string) $client->getResponse()->getContent());
    }

    public function testInvitationAcceptedShowsWelcomeWhenAuthenticated(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/invitations/accepted');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Bienvenue', $html);
        self::assertStringContainsString('Accéder à l\'application', $html);
    }

    public function testInvitationAcceptedRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/invitations/accepted');
        self::assertResponseRedirects('/login');
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

    public function testPwaManifestServiceWorkerAndOfflineRender(): void
    {
        $client = static::createClient();

        // Manifest localisé, servi à la racine du scope.
        $client->request('GET', '/manifest.webmanifest');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/manifest+json', (string) $client->getResponse()->headers->get('Content-Type'));
        $manifest = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('"CoParentPlanner"', $manifest);
        self::assertStringContainsString('"standalone"', $manifest);
        self::assertStringContainsString('#C2643D', $manifest);

        // Service worker servi à la racine (en-tête Service-Worker-Allowed) et non mis en cache long.
        $client->request('GET', '/sw.js');
        self::assertResponseIsSuccessful();
        self::assertSame('/', $client->getResponse()->headers->get('Service-Worker-Allowed'));
        $sw = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('offline-mutation', $sw);
        self::assertStringContainsString('caches', $sw);

        // Page hors-ligne dédiée.
        $client->request('GET', '/offline');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('hors-ligne', mb_strtolower((string) $client->getResponse()->getContent()));
    }

    public function testAppShellRegistersPwa(): void
    {
        $client = static::createClient();
        $this->createUser($client, true);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('rel="manifest"', $html);
        self::assertStringContainsString('data-controller="pwa"', $html);
        self::assertStringContainsString('name="theme-color"', $html);
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

    public function testExchangesPageRendersEmpty(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $client->request('GET', '/echanges');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Échanges', $html);
        self::assertStringContainsString('Aucun échange pour le moment', $html);
        self::assertStringContainsString('Nouvel échange', $html); // parent admin → exchanges:edit
        self::assertStringContainsString('turbo-stream-source', $html);
        self::assertStringContainsString('href="/echanges"', $html); // onglet dans la barre latérale
    }

    public function testExchangesListAndDetailWithBadge(): void
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

        $exchange = (new \App\Entity\Exchange())
            ->setFamily($family)
            ->setType(\App\Enum\ExchangeType::Discussion)
            ->setTitle('Organisation des vacances')
            ->setAuthorMembership($membership);
        $em->persist($exchange);
        $em->flush();

        // Liste : l'échange figure ; le badge de navigation affiche le nombre d'ouverts.
        $client->request('GET', '/echanges');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Organisation des vacances', $html);
        self::assertStringContainsString('id="exchanges-badge"', $html);

        // Détail : sélection via ?exchange= → fil (état vide localisé) + formulaire de message.
        $client->request('GET', '/echanges?exchange='.$exchange->getId());
        self::assertResponseIsSuccessful();
        $detail = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Aucun message pour le moment', $detail);
        self::assertStringContainsString('Écrire un message', $detail);
    }
}
