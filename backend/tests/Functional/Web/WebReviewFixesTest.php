<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Child;
use App\Entity\CustodyDay;
use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\CustodyDaySource;
use App\Enum\CustodyPatternKind;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Non-régression du front web pour la revue `specs/revue-web-2026-08.md`.
 *
 * Un test par constat corrigé, nommé d'après lui. Ces défauts avaient tous en commun d'être
 * invisibles depuis le code seul : ils ne se voyaient qu'en exerçant l'écran (session survivante,
 * sous-titre rendu avant le contrôle de droit, cookie d'abonnement accordé à tort…).
 */
final class WebReviewFixesTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';
    /** En-tête envoyé par tout navigateur moderne ; c'est lui qui valide le CSRF sans session. */
    private const array SAME_ORIGIN = ['HTTP_SEC_FETCH_SITE' => 'same-origin'];

    // ───────────────────────── C1 / C22 — déconnexion ─────────────────────────

    /**
     * C1 (P0) — la déconnexion doit réellement fermer l'accès au front.
     *
     * Le pare-feu web n'était pas `stateless` : Symfony recopiait le token dans la session PHP, et
     * `PHPSESSID` — que `logout()` n'invalidait pas — continuait d'authentifier tous les écrans,
     * notes sur les enfants comprises, alors que l'API répondait 401.
     */
    public function testLogoutEndsWebAccess(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/children');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/logout', parameters: ['_csrf_token' => $this->csrfToken($client)], server: self::SAME_ORIGIN);
        self::assertResponseRedirects('/login');

        // Les cookies JWT sont effacés : plus aucune preuve d'authentification ne subsiste.
        $client->request('GET', '/children');
        self::assertResponseRedirects('/login');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    /** C1 — le pare-feu web doit rester sans état : c'est ce qui interdit la session d'authentifier. */
    public function testWebFirewallIsStateless(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $cookies = array_keys($client->getCookieJar()->allValues('http://localhost/'));
        self::assertNotContains('PHPSESSID', $cookies, 'Le front web ne doit poser aucune session : '
            .'elle survivrait à la déconnexion et continuerait d\'authentifier les écrans (C1).');
    }

    /**
     * C22 — `POST /logout` doit être protégé du CSRF. Sans en-tête d'origine ni jeton double-submit,
     * la requête est rejetée : un site tiers ne peut plus forcer la déconnexion.
     */
    public function testLogoutRejectsCrossSiteRequest(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $token = $this->csrfToken($client);
        $client->request('POST', '/logout', parameters: ['_csrf_token' => $token], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseRedirects('/');

        // Toujours connecté : la tentative n'a rien déconnecté.
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    // ───────────────────────── C2 — temps réel ─────────────────────────

    /**
     * C2 (P1) — l'abonnement doit être **authentifié**, sinon aucun écran ne reçoit jamais rien : les
     * quatre publishers publient en privé, et une mise à jour privée n'est délivrée qu'à un abonné
     * autorisé. Deux conditions, vérifiées ici : l'élément de UX Turbo (le `<turbo-stream-source>`
     * natif de Turbo ouvre `new EventSource(src)` sans `withCredentials` et ignore `private`), et
     * l'attribut `private` qui déclenche l'envoi du cookie.
     */
    public function testRealtimeSourcesCarryPrivateAttribute(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedPatternedFamily(custody: RightLevel::Edit);
        $child = (new Child())->setFirstName('Léa')->setFamily($this->family());
        $this->em()->persist($child);
        $this->em()->flush();
        $this->login($client);

        // Les quatre écrans temps réel du projet, chacun avec de quoi rendre sa balise.
        foreach (['/garde', '/agenda', '/echanges', '/children?child='.$child->getId()] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $html = (string) $client->getResponse()->getContent();
            self::assertStringContainsString('<turbo-mercure-stream-source private', $html, "Abonnement anonyme sur {$path} (C2).");
        }
    }

    // ───────────────────── C7 / C15 / C16 — /garde et abonnement ─────────────────────

    /**
     * C7 — `/garde` ne doit rien dire du planning à un membre sans droit `custody` : le sous-titre
     * (rythme + heure d'échange) était rendu dans le `<header>`, donc AVANT le branchement sur le
     * droit, rendant le web plus permissif que l'API (qui répond 403).
     *
     * C15 — et aucun cookie d'abonnement Mercure ne doit lui être délivré pour le topic `custody`.
     */
    public function testGardeLeaksNothingWithoutCustodyRight(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedPatternedFamily(custody: RightLevel::None);
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString("Vous n'avez pas accès au planning de garde", $html);
        self::assertStringNotContainsString('Une semaine sur deux', $html);
        self::assertStringNotContainsString('19:00', $html);
        self::assertNull(
            $client->getCookieJar()->get('mercureAuthorization', '/.well-known/mercure'),
            'Abonnement temps réel `custody` accordé sans le droit correspondant (C15).',
        );
    }

    /** C16 — le JWT d'abonnement doit être borné dans le temps, donc révocable. */
    public function testMercureSubscriptionTokenExpires(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedPatternedFamily(custody: RightLevel::Edit);
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();

        $cookie = $client->getCookieJar()->get('mercureAuthorization', '/.well-known/mercure');
        self::assertNotNull($cookie, 'Le membre habilité doit recevoir son abonnement.');

        $claims = self::decodeJwtPayload($cookie->getValue());
        self::assertArrayHasKey('exp', $claims, "Le JWT d'abonnement Mercure n'expire jamais (C16).");
        self::assertArrayHasKey('iat', $claims);
        self::assertGreaterThan(time(), $claims['exp']);
        self::assertSame(
            ['/families/'.$this->familyId().'/custody'],
            $claims['mercure']['subscribe'] ?? null,
            "L'abonnement doit rester scopé au seul topic garde de la famille active.",
        );
    }

    // ───────────────────── C13 / C17 / C19 / C21 — écrans ─────────────────────

    /**
     * C13 — les sélecteurs « Parent A » et « Parent B » étaient pré-remplis avec le MÊME membre :
     * l'enregistrement du rythme ne pouvait qu'échouer (l'API exige deux parents distincts).
     */
    public function testRhythmBuilderPreselectsTwoDistinctParents(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [, $second] = $this->seedTwoParentFamily();
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        $selectedA = self::selectedOption($html, 'g-pa');
        $selectedB = self::selectedOption($html, 'g-pb');
        self::assertNotNull($selectedA);
        self::assertNotNull($selectedB);
        self::assertNotSame($selectedA, $selectedB, 'Parent A et Parent B pré-remplis à l\'identique (C13).');
        self::assertContains((string) $second->getId(), [$selectedA, $selectedB]);
        // Les deux sélecteurs restent synchronisés côté client.
        self::assertStringContainsString('change->garde#syncParents', $html);
    }

    /**
     * C17 — la navigation de `/settings` proposait « Exceptions » et « Vacances » alors que les
     * sections correspondantes ne sont pas rendues sans second parent : cliquer ne faisait rien.
     */
    public function testSettingsNavigationHasNoDanglingAnchors(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true); // parent admin, seul parent de la famille
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        foreach (['sec-exceptions', 'sec-holidays'] as $anchor) {
            if (str_contains($html, 'href="#'.$anchor.'"')) {
                self::assertStringContainsString('id="'.$anchor.'"', $html, "Ancre morte : #{$anchor} (C17).");
            }
        }
        self::assertStringNotContainsString('href="#sec-exceptions"', $html);
    }

    /** C21 — pluriel ICU, pas « 1 personne(s) ». */
    public function testMembersCountUsesIcuPlural(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createUser(withFamily: true);
        $this->login($client);

        $client->request('GET', '/settings');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('personne(s)', $html);
        self::assertStringContainsString('1 personne dans la famille.', $html);
    }

    /**
     * C19 — « Statistiques du mois » portait sur les seuls jours matérialisés sans le dire : un mois
     * couvert à 6/31 affichait « 66,7 % / 33,3 % » comme s'il était complet.
     */
    public function testMonthStatsDisclosePartialCoverage(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedPatternedFamily(custody: RightLevel::Edit, withDays: 3);
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('déjà planifiés de ce mois', $html);
    }

    // ───────────────────────────── outillage ─────────────────────────────

    /** @return array<string, mixed> */
    private static function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts);
        $json = base64_decode(strtr($parts[1], '-_', '+/'), true);
        self::assertIsString($json);
        $claims = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($claims);

        return $claims;
    }

    /** Valeur de l'`<option selected>` du `<select id="...">` demandé. */
    private static function selectedOption(string $html, string $selectId): ?string
    {
        if (1 !== preg_match('#<select id="'.preg_quote($selectId, '#').'".*?</select>#s', $html, $select)) {
            return null;
        }
        if (1 !== preg_match('#<option value="([^"]+)"\s+selected#', $select[0], $option)) {
            return null;
        }

        return $option[1];
    }

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function family(): Family
    {
        $family = $this->em()->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);

        return $family;
    }

    private function familyId(): string
    {
        return (string) $this->family()->getId();
    }

    /**
     * Jeton CSRF tel qu'un navigateur le renverrait : la valeur rendue dans le formulaire. Sans JS,
     * c'est le nom du cookie double-submit, et la validation repose sur l'origine same-origin —
     * exactement le scénario que `testLogoutRejectsCrossSiteRequest` met en échec.
     */
    private function csrfToken(KernelBrowser $client): string
    {
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $field = $client->getCrawler()->filter('input[name="_csrf_token"]')->first();
        self::assertGreaterThan(0, $field->count(), 'Aucun jeton CSRF dans le formulaire de déconnexion (C22).');

        return (string) $field->attr('value');
    }

    private function createUser(bool $withFamily): User
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
                ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
            $this->em()->persist($family);
            $this->em()->persist($membership);
        }

        $this->em()->flush();

        return $user;
    }

    /** Famille séparée à deux parents, rythme « une semaine sur deux » ancré au 1er du mois courant. */
    private function seedPatternedFamily(RightLevel $custody, int $withDays = 0): void
    {
        $user = $this->createUser(withFamily: true);
        $em = $this->em();
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);
        $membership = $em->getRepository(Membership::class)->findOneBy(['family' => $family, 'user' => $user]);
        self::assertInstanceOf(Membership::class, $membership);

        if (RightLevel::None === $custody) {
            // Beau-parent sans droit `custody` : la sonde d'autorisation de la revue.
            $membership->setRole(MembershipRole::StepParent)->setIsAdmin(false)
                ->addRight(new Right(RightDomain::Custody, RightLevel::None));
        }

        $other = (new User())->setEmail('web-b@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $other->setPasswordHash('x');
        $parentB = (new Membership())->setUser($other)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);
        $parentA = RightLevel::None === $custody
            ? $parentB
            : $membership;
        if (RightLevel::None === $custody) {
            $extra = (new User())->setEmail('web-a@example.com')->setStatus(UserStatus::Active)
                ->setEmailVerifiedAt(Clock::get()->now());
            $extra->setPasswordHash('x');
            $parentA = (new Membership())->setUser($extra)->setFamily($family)
                ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
            $em->persist($extra);
            $em->persist($parentA);
        }
        $em->persist($other);
        $em->persist($parentB);

        $monthStart = Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->modify('first day of this month')->setTime(0, 0);
        $pattern = (new CustodyPattern())
            ->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate($monthStart)
            ->setHandoverWeekday(0)
            ->setHandoverTime('19:00')
            ->setParentA($parentA)
            ->setParentB($parentB);
        $em->persist($pattern);

        for ($i = 0; $i < $withDays; ++$i) {
            $em->persist((new CustodyDay())
                ->setFamily($family)
                ->setDate($monthStart->modify("+{$i} day"))
                ->setParentMembership(0 === $i % 2 ? $parentA : $parentB)
                ->setSource(CustodyDaySource::Base));
        }

        $em->flush();
    }

    /**
     * @return array{0: Membership, 1: Membership} les deux parents (le premier est le compte connecté)
     */
    private function seedTwoParentFamily(): array
    {
        $user = $this->createUser(withFamily: true);
        $em = $this->em();
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);
        $first = $em->getRepository(Membership::class)->findOneBy(['family' => $family, 'user' => $user]);
        self::assertInstanceOf(Membership::class, $first);

        $other = (new User())->setEmail('web-b@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $other->setPasswordHash('x');
        $second = (new Membership())->setUser($other)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);
        $em->persist($other);
        $em->persist($second);
        $em->flush();

        return [$first, $second];
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
