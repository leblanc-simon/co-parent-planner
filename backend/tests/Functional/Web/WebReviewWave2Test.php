<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\UserStatus;
use App\Web\NextTarget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Vague 2 de l'étape 6 de `specs/revue-web-desktop-2026-07.md` — **parcours cassés** :
 *
 * - **6.8** les liens des e-mails de notification perdaient leur cible (redirection `/login` sans
 *   `next`, puis accueil), et toute URL web inconnue tombait sur la page d'erreur brute de Symfony ;
 * - **6.10** créer une seconde famille était impossible depuis l'interface ;
 * - **6.6** « Familles liées » réclamait un UUID qu'aucun écran n'affichait.
 *
 * Ces trois défauts se ressemblent : la fonctionnalité existe côté serveur, mais **aucun chemin
 * d'interface n'y mène**. Ils ne se voient donc pas dans un test d'API.
 */
final class WebReviewWave2Test extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    // ───────────────────────── 6.8 — la cible des liens d'e-mail ─────────────────────────

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function nextTargets(): iterable
    {
        yield 'cible interne' => ['/echanges?exchange=abc', '/echanges?exchange=abc'];
        yield 'domaine externe' => ['https://evil.example/x', null];
        yield 'relatif au protocole' => ['//evil.example/x', null];
        yield 'relatif au protocole antislash' => ['/\\evil.example/x', null];
        yield 'chemin relatif' => ['garde', null];
        yield 'injection de saut de ligne' => ["/garde\nLocation: https://evil.example", null];
        yield 'vide' => ['', null];
    }

    /**
     * Une cible fournie de l'extérieur ne doit jamais devenir un tremplin : le paramètre `next`
     * arrive dans une URL, donc potentiellement d'un lien forgé par un tiers.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nextTargets')]
    public function testNextTargetAcceptsOnlyInternalPaths(string $candidate, ?string $expected): void
    {
        self::assertSame($expected, NextTarget::sanitize($candidate));
    }

    /** Les écrans d'authentification ne sont pas des cibles : y revenir connecté n'a pas de sens. */
    public function testAuthPagesAreNotKeptAsTarget(): void
    {
        self::assertNull(NextTarget::of(Request::create('/login?expired=1')));
        self::assertNull(NextTarget::of(Request::create('/reset-password?token=x')));
        self::assertSame('/garde', NextTarget::of(Request::create('/garde')));
    }

    /**
     * Le parcours complet du lien d'e-mail : page métier demandée hors session → connexion qui
     * conserve la cible → retour sur la page demandée, et non sur l'accueil.
     */
    public function testEmailLinkKeepsItsTargetThroughLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/echanges?exchange=abc');
        // Le générateur d'URL n'échappe pas le `?` d'une valeur de query : la chaîne reste
        // lisible et se relit correctement (le premier `?` ouvre la query, les suivants sont
        // littéraux).
        self::assertResponseRedirects('/login?next=/echanges?exchange%3Dabc');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        // Le formulaire de connexion renvoie vers la page demandée (et non vers `/`).
        $redirect = $client->getCrawler()->filter('form[data-auth-form-redirect-value]')->first();
        self::assertSame('/echanges?exchange=abc', $redirect->attr('data-auth-form-redirect-value'));
    }

    /** Déjà connecté, `/login?next=…` mène directement à la cible plutôt qu'à l'accueil. */
    public function testLoggedInUserFollowsTargetImmediately(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/login?next=/garde');
        self::assertResponseRedirects('/garde');
    }

    /**
     * `ProblemExceptionSubscriber` ne traite que `/api` : côté web, toute URL inconnue tombait sur
     * la page par défaut de Symfony — « Oops! An Error Occurred », en anglais, sans retour possible.
     *
     * Le gabarit ne s'applique qu'en environnement non-debug ; ce qui se vérifie ici est que
     * l'**override est au bon endroit** (`templates/bundles/TwigBundle/Exception/`), la faute la
     * plus facile à commettre et la plus silencieuse : un dossier mal placé ne produit aucune erreur.
     */
    public function testWebErrorPagesAreOverridden(): void
    {
        $twig = static::getContainer()->get('twig');
        self::assertInstanceOf(\Twig\Environment::class, $twig);
        // `base.html.twig` lit `app.request` : la page d'erreur est toujours rendue dans le
        // contexte d'une requête, il faut donc lui en fournir une.
        $stack = static::getContainer()->get('request_stack');
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\RequestStack::class, $stack);
        $stack->push(Request::create('/page-inconnue'));

        $generic = $twig->render('@Twig/Exception/error.html.twig', ['status_code' => 500, 'status_text' => 'Internal Server Error']);
        self::assertStringContainsString('Une erreur est survenue', $generic);
        self::assertStringContainsString('Retour à l\'accueil', $generic);

        $notFound = $twig->render('@Twig/Exception/error404.html.twig', ['status_code' => 404, 'status_text' => 'Not Found']);
        self::assertStringContainsString('Page introuvable', $notFound);
        self::assertStringContainsString('Retour à l\'accueil', $notFound);
    }

    // ───────────────────────── 6.10 — créer une seconde famille ─────────────────────────

    /**
     * `/onboarding` répondait 200 mais aucun gabarit ne le liait : depuis l'interface, un compte
     * ayant déjà une famille ne pouvait pas en créer une seconde.
     */
    public function testFamilySwitcherOffersFamilyCreation(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $action = $client->getCrawler()->filter('.family-select__menu a[href="/onboarding"]');
        self::assertGreaterThan(0, $action->count(), 'Le sélecteur de famille n\'offre aucune action de création (6.10).');
    }

    /** L'écran s'adapte : « votre première famille » n'a de sens qu'une fois, et il faut un retour. */
    public function testOnboardingAdaptsToAnAdditionalFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/onboarding');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Nouvelle famille', $html);
        self::assertStringNotContainsString('votre première famille', $html);
        // Un compte qui a déjà une famille arrive ici volontairement : il lui faut un retour,
        // pas un bouton de déconnexion.
        self::assertGreaterThan(0, $client->getCrawler()->filter('a[href="/"]')->count());
        // La famille créée devient la famille active, sinon on retombe sur l'ancienne.
        self::assertGreaterThan(
            0,
            $client->getCrawler()->filter('form[data-auth-form-activate-family-value]')->count(),
        );
    }

    /** Sans famille, l'écran reste celui de la première : bienvenue et déconnexion. */
    public function testOnboardingKeepsWelcomeForAFirstFamily(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: false);
        $this->login($client);

        $client->request('GET', '/onboarding');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('votre première famille', $html);
        self::assertStringContainsString('Se déconnecter', $html);
    }

    // ───────────────────────── 6.6 — code de partage des familles liées ─────────────────────────

    /**
     * Le formulaire réclamait « l'UUID de la famille », valeur qu'aucun écran n'affichait : lier
     * deux familles était impossible de bout en bout. Le code est désormais montré et copiable.
     */
    public function testLinkedFamiliesShowTheShareCode(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $family = $this->em()->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        $code = $crawler->filter('#l-own');
        self::assertGreaterThan(0, $code->count(), 'Le code de partage de la famille n\'est affiché nulle part (6.6).');
        self::assertSame((string) $family->getId(), $code->attr('value'));
        // Attribut booléen : le Crawler rend une chaîne vide, pas `null`.
        self::assertNotNull($code->attr('readonly'));
        self::assertGreaterThan(0, $crawler->filter('[data-action="settings#copyShareCode"]')->count());

        // Le champ de saisie demande un code, plus un UUID à deviner.
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Code de partage de l\'autre famille', $html);
        self::assertStringNotContainsString('UUID de la famille', $html);
    }

    /** Un non-admin ne gère pas les liens : ni code, ni formulaire. */
    public function testShareCodeIsAdminOnly(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true, admin: false);
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $client->getCrawler()->filter('#l-own')->count());
    }

    // ───────────────────────── fixtures ─────────────────────────

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function createUser(bool $withFamily, bool $admin = true): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())
            ->setEmail('web@example.com')
            ->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);

        if ($withFamily) {
            $family = (new Family())->setName('Famille Web')->setType(FamilyType::Separated);
            $membership = (new Membership())->setUser($user)->setFamily($family)
                ->setRole(MembershipRole::Parent)->setIsAdmin($admin)->setStatus(MembershipStatus::Active);
            $this->em()->persist($family);
            $this->em()->persist($membership);
        }

        $this->em()->flush();

        return $user;
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
