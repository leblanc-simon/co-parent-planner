<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Child;
use App\Entity\CustodyDay;
use App\Entity\CustodyPattern;
use App\Entity\Event;
use App\Entity\Exchange;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\OverlayPreference;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\CustodyDaySource;
use App\Enum\CustodyPatternKind;
use App\Enum\EventCategory;
use App\Enum\ExchangeStatus;
use App\Enum\ExchangeType;
use App\Enum\FamilyLinkStatus;
use App\Enum\FamilyType;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\OverlayView;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
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

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        [$family] = $this->activeFamilyOf($em);
        // Un rythme de garde suppose DEUX parents. Ce test n'en créait qu'un et affirmait pourtant la
        // présence du builder : il validait donc un formulaire qui répondait 422 à tout coup
        // (revue 6.7). Le cas à un seul parent a désormais son propre test.
        $this->addSecondParent($em, $family);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Compte', $html);
        self::assertStringContainsString('Réglages de la famille', $html);
        self::assertStringContainsString('Inviter une personne', $html);
        self::assertStringContainsString('Familles liées', $html);
        // L'édition du rythme de garde a été rapatriée ici (famille séparée + parent admin → custody:edit).
        self::assertStringContainsString('Rythme de garde', $html);
        self::assertStringContainsString('Enregistrer le rythme', $html);
        self::assertStringContainsString('data-action="submit->garde#savePattern"', $html);
        // Le doublon inerte de « jour / heure d'échange » a été retiré du § Famille (revue 6.9) :
        // il n'était lu par personne et employait la convention inverse de celle du rythme.
        self::assertStringNotContainsString('id="f-hw"', $html);
        self::assertStringNotContainsString('id="f-ht"', $html);
    }

    /**
     * Famille à **un seul parent actif** (revue 6.7) : le builder de rythme est remplacé par la
     * condition manquante et l'action qui la lève, au lieu d'un formulaire dont « Enregistrer »
     * répondait 422 sans expliquer pourquoi — l'utilisateur bouclait alors entre `/settings` et
     * `/garde` pendant tout son premier jour d'usage.
     */
    public function testSettingsExplainsMissingSecondParentInsteadOfBrokenRhythmBuilder(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true); // séparée, un seul parent
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Rythme de garde', $html);
        self::assertStringContainsString("Cette famille ne compte qu'un seul parent", $html);
        self::assertStringContainsString('Inviter le second parent', $html);
        // Aucun formulaire de rythme, donc aucun 422 possible : ni sélecteurs de parents, ni bouton.
        self::assertStringNotContainsString('data-action="submit->garde#savePattern"', $html);
        self::assertStringNotContainsString('id="g-pa"', $html);
        self::assertStringNotContainsString('id="g-pb"', $html);
        self::assertStringNotContainsString('Enregistrer le rythme', $html);
    }

    /**
     * Ajoute un second membership parent actif à la famille : un rythme de garde en exige deux
     * (titulaires distincts), et plusieurs écrans se comportent différemment en dessous.
     */
    private function addSecondParent(
        EntityManagerInterface $em,
        Family $family,
        string $email = 'web2@example.com',
        ?string $fullName = null,
    ): Membership {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail($email)->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        if (null !== $fullName) {
            $user->setFullName($fullName);
        }

        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);

        $em->persist($user);
        $em->persist($membership);
        $em->flush();

        return $membership;
    }

    /**
     * Crée web@example.com dans une famille du type donné, avec rôle/droits précis, et le connecte.
     *
     * @param list<Right> $customRights
     */
    private function createMemberAndLogin(
        KernelBrowser $client,
        FamilyType $type,
        MembershipRole $role,
        bool $admin,
        array $customRights = [],
    ): void {
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

        $family = (new Family())->setName('Famille Web')->setType($type);
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole($role)->setIsAdmin($admin)->setStatus(MembershipStatus::Active);
        foreach ($customRights as $right) {
            $membership->addRight($right);
        }
        $em->persist($family);
        $em->persist($membership);
        $em->flush();

        $this->login($client);
    }

    public function testSettingsHidesCustodyEditingForViewOnlyMember(): void
    {
        // Invariant : un membre sans droit custody:edit (ici step_parent en lecture seule) ne doit
        // jamais voir le builder de rythme — même en famille séparée (garde active).
        $client = static::createClient();
        $client->disableReboot();
        $this->createMemberAndLogin(
            $client,
            FamilyType::Separated,
            MembershipRole::StepParent,
            admin: false,
            customRights: [new Right(RightDomain::Custody, RightLevel::View)],
        );

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Compte', $html); // la page rend bien pour ce membre
        self::assertStringNotContainsString('data-action="submit->garde#savePattern"', $html);
        self::assertStringNotContainsString('Enregistrer le rythme', $html);
        self::assertStringNotContainsString('href="#sec-custody"', $html);
    }

    /**
     * Revue 6.12 — l'interface ne doit proposer que ce que les droits autorisent. Un membre sans
     * aucun domaine voyait : les onglets Garde, Agenda et Enfants (qui menaient tous à un écran de
     * refus), l'encart « Le planning de garde s'affichera ici. » — une promesse que rien ne tiendrait
     * jamais pour lui — et le bouton « Nouvel événement » alors qu'il ne peut rien créer.
     */
    public function testNavigationAndHomeHideDomainsWithoutRights(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        // Beau-parent sans aucun droit : `customRights` vide ⇒ tous les domaines à `none`.
        $this->createMemberAndLogin(
            $client,
            FamilyType::Separated,
            MembershipRole::StepParent,
            admin: false,
            customRights: [new Right(RightDomain::Custody, RightLevel::None)],
        );

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // La page rend, mais sans onglet menant à un refus…
        self::assertStringContainsString('Bonjour', $html);
        self::assertStringNotContainsString('href="/garde"', $html);
        // …sans promesse intenable…
        self::assertStringNotContainsString("Le planning de garde s'affichera ici", $html);
        // …et sans invitation à créer ce qu'il ne peut pas créer.
        self::assertStringNotContainsString('Nouvel événement', $html);
    }

    public function testSettingsHidesCustodyEditingForIntactFamily(): void
    {
        // Une famille unie n'a pas de garde : aucune section de rythme, même pour un parent admin.
        $client = static::createClient();
        $client->disableReboot();
        $this->createMemberAndLogin($client, FamilyType::Intact, MembershipRole::Parent, admin: true);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('data-action="submit->garde#savePattern"', $html);
        self::assertStringNotContainsString('href="#sec-custody"', $html);
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
        // Élément de UX Turbo, seul à honorer `private` (abonnement authentifié — revue 2026-08 C2).
        self::assertStringContainsString('turbo-mercure-stream-source private', $html);
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

    public function testHomeShowsOpenExchange(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        [$family, $membership] = $this->activeFamilyOf($em);

        $exchange = (new Exchange())
            ->setFamily($family)
            ->setType(ExchangeType::Decision)
            ->setTitle('Choix école 2026')
            ->setStatus(ExchangeStatus::Open)
            ->setAuthorMembership($membership);
        $em->persist($exchange);
        $em->flush();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Encart « À décider ensemble » câblé (US-HOME-1) : la décision ouverte figure en carte.
        self::assertStringContainsString('À décider ensemble', $html);
        self::assertStringContainsString('Choix école 2026', $html);
        self::assertStringContainsString('exchange-card', $html);
    }

    public function testHomeShowsRecentNote(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        [$family, $membership] = $this->activeFamilyOf($em);

        $child = (new Child())->setFamily($family)->setFirstName('Léa')->setColor('#C2643D');
        $em->persist($child);
        $note = (new Note())
            ->setChild($child)
            ->setAuthorMembership($membership)
            ->setText('Rendez-vous dentiste jeudi')
            ->setNoteDate(Clock::get()->now());
        $em->persist($note);
        $em->flush();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Encart « Dernières notes » câblé : texte (déchiffré) + enfant concerné.
        self::assertStringContainsString('Dernières notes', $html);
        self::assertStringContainsString('Rendez-vous dentiste jeudi', $html);
        self::assertStringContainsString('sur Léa', $html);
    }

    public function testHomeShowsCurrentCustodyWeek(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true); // FamilyType::Separated → garde active
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        [$family, $parentA] = $this->activeFamilyOf($em);

        // Un rythme exige deux parents distincts : on ajoute un second membership parent.
        $user2 = (new User())->setEmail('web2@example.com')->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user2->setPasswordHash($hasher->hashPassword($user2, self::PASSWORD));
        $parentB = (new Membership())->setUser($user2)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);
        $em->persist($user2);
        $em->persist($parentB);

        $pattern = (new CustodyPattern())
            ->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate(Clock::get()->now()->setTime(0, 0))
            ->setHandoverWeekday(0)
            ->setHandoverTime('19:00')
            ->setParentA($parentA)
            ->setParentB($parentB);
        $em->persist($pattern);

        // Jour de garde matérialisé pour aujourd'hui, attribué au parent A (slot 'A').
        $today = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $day = (new CustodyDay())->setFamily($family)->setDate($today)
            ->setParentMembership($parentA)->setSource(CustodyDaySource::Base);
        $em->persist($day);
        $em->flush();

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        // Encart « Cette semaine » câblé : mini-semaine colorée, jour du parent A présent.
        self::assertStringContainsString('Cette semaine', $html);
        self::assertStringContainsString('home-custody', $html);
        self::assertStringContainsString('home-custody__day--a', $html);
        self::assertStringContainsString('web@example.com', $html); // nom (fallback e-mail) du parent A dans la légende
    }

    /**
     * Récupère la Family « Famille Web » et son membership actif, créés par {@see self::createUser}.
     *
     * @return array{0: Family, 1: Membership}
     */
    private function activeFamilyOf(EntityManagerInterface $em): array
    {
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);
        $membership = $em->getRepository(Membership::class)->findOneBy(['family' => $family]);
        self::assertInstanceOf(Membership::class, $membership);

        return [$family, $membership];
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
        // Sans rythme configuré : message d'état vide.
        self::assertStringContainsString('Aucun rythme de garde configuré', $html);
        // Cette famille n'a qu'un parent : renvoyer vers le builder de rythme entretenait une boucle
        // /garde → /settings → 422 → /garde (revue 6.7). On énonce la condition manquante.
        self::assertStringContainsString('deux parents', $html);
        self::assertStringContainsString('#sec-members', $html);
        self::assertStringNotContainsString('#sec-custody', $html);
        // Le builder de rythme ne vit plus sur l'écran Garde (déplacé vers Paramètres).
        self::assertStringNotContainsString('data-action="submit->garde#savePattern"', $html);
        self::assertStringNotContainsString('Enregistrer le rythme', $html);
        // L'onglet Garde figure dans la barre latérale d'une famille séparée.
        self::assertStringContainsString('href="/garde"', $html);
        // Abonnement temps réel câblé.
        // Élément de UX Turbo, seul à honorer `private` (abonnement authentifié — revue 2026-08 C2).
        self::assertStringContainsString('turbo-mercure-stream-source private', $html);
    }

    /**
     * Contrepartie du test précédent : **deux** parents et pas encore de rythme. Le renvoi vers le
     * builder est alors légitime, et c'est le cas qu'aucun test ne couvrait — les fixtures web ne
     * créaient qu'un parent.
     */
    public function testGardeLinksToRhythmBuilderWhenSecondParentIsPresent(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        [$family] = $this->activeFamilyOf($em);
        $this->addSecondParent($em, $family);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Aucun rythme de garde configuré', $html);
        self::assertStringContainsString('#sec-custody', $html);
        self::assertStringNotContainsString('#sec-members', $html);
    }

    /**
     * Garde-fou d'accessibilité (cdc §8, WCAG 1.4.1) : l'attribution d'un jour de garde ne doit
     * JAMAIS dépendre de la seule couleur. Les deux fonds prescrits par le §8 ne se distinguent que
     * par la teinte — 1,12:1 de contraste de luminance, 1,08:1 en protanopie simulée sur les
     * bordures — donc le planning devenait illisible en niveaux de gris, en daltonisme rouge-vert et
     * en contraste forcé. Miroir du test Flutter « marqueur non chromatique ».
     *
     * Le test ne se contente pas de trouver le marqueur : il vérifie qu'il porte l'initiale RÉELLE
     * de chaque parent et que les deux diffèrent — un marqueur identique des deux côtés ne
     * distinguerait rien.
     */
    public function testGardeCarriesNonColourParentMarker(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser($client, withFamily: true);
        $this->login($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        [$family, $parentA] = $this->activeFamilyOf($em);

        // Noms explicites des deux côtés : sans eux le repli e-mail donnerait « A »/« B », et le
        // test ne prouverait pas que les initiales réelles sont utilisées.
        $parentA->getUser()->setFullName('Sophie Martin');

        $userB = (new User())->setEmail('marc@example.com')->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $userB->setPasswordHash($hasher->hashPassword($userB, self::PASSWORD))->setFullName('Marc Durand');
        $parentB = (new Membership())->setUser($userB)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);

        $today = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $pattern = (new CustodyPattern())
            ->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate($today)
            ->setHandoverWeekday(0)
            ->setHandoverTime('19:00')
            ->setParentA($parentA)
            ->setParentB($parentB);

        // Deux jours consécutifs, un par parent : le marqueur doit différer de l'un à l'autre.
        $dayA = (new CustodyDay())->setFamily($family)->setDate($today)
            ->setParentMembership($parentA)->setSource(CustodyDaySource::Base);
        $dayB = (new CustodyDay())->setFamily($family)->setDate($today->modify('+1 day'))
            ->setParentMembership($parentB)->setSource(CustodyDaySource::Base);

        foreach ([$userB, $parentB, $pattern, $dayA, $dayB] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $client->request('GET', '/garde?month='.$today->format('Y-m'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Le marqueur existe et porte l'initiale de chaque parent (« Sophie » → S, « Marc » → M).
        self::assertStringContainsString('garde-day__parent', $html);
        self::assertStringContainsString('<span class="garde-day__parent" aria-hidden="true">S</span>', $html);
        self::assertStringContainsString('<span class="garde-day__parent" aria-hidden="true">M</span>', $html);
        // La légende porte le même marqueur : purement colorée, elle n'expliquerait rien.
        self::assertStringContainsString('garde-dot--a" aria-hidden="true">S<', $html);
        self::assertStringContainsString('garde-dot--b" aria-hidden="true">M<', $html);
        // Les lecteurs d'écran reçoivent le nom en clair, jamais l'initiale seule.
        self::assertStringContainsString('chez Sophie Martin', $html);
        self::assertStringContainsString('chez Marc Durand', $html);

        // Même invariant sur la mini-semaine de l'accueil (elle était à 1,09:1).
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $home = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('home-custody__parent', $home);
        self::assertStringContainsString('<span class="home-custody__parent" aria-hidden="true">S</span>', $home);
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
        // Élément de UX Turbo, seul à honorer `private` (abonnement authentifié — revue 2026-08 C2).
        self::assertStringContainsString('turbo-mercure-stream-source private', $html);
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

        $exchange = (new Exchange())
            ->setFamily($family)
            ->setType(ExchangeType::Discussion)
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
