<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Child;
use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\ExpenseCategory;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\ShareType;
use App\Enum\UserStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Écran Dépenses (Lot 10) en rendu serveur : onglet conditionnel au module, solde et répartition,
 * liste, et refus d'accès. Le module se pilote depuis les réglages de la famille (US-DEP-6).
 */
final class DepensesPageTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    /**
     * @param list<Right> $rights
     */
    private function seed(
        KernelBrowser $client,
        bool $expensesEnabled,
        MembershipRole $role = MembershipRole::Parent,
        array $rights = [],
    ): Family {
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail('dep-web@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now())->setFullName('Alice Martin');
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);

        $family = (new Family())->setName('Famille Dépenses')->setType(FamilyType::Separated)
            ->setExpensesEnabled($expensesEnabled);
        $membership = (new Membership())->setUser($user)->setFamily($family)
            ->setRole($role)->setIsAdmin(MembershipRole::Parent === $role)->setStatus(MembershipStatus::Active);
        foreach ($rights as $right) {
            $membership->addRight($right);
        }
        $em->persist($family);
        $em->persist($membership);
        $em->flush();

        $client->request('POST', '/api/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['email' => 'dep-web@example.com', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return $family;
    }

    private function seedExpense(Family $family, int $amountCents = 4250): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        $parents = $em->getRepository(Membership::class)->findBy(['family' => $family]);
        $payer = $parents[0];
        self::assertInstanceOf(Membership::class, $payer);

        $second = (new User())->setEmail('dep-web2@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now())->setFullName('Bob Durand');
        $second->setPasswordHash('x');
        $other = (new Membership())->setUser($second)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setStatus(MembershipStatus::Active);
        $em->persist($second);
        $em->persist($other);

        $child = (new Child())->setFamily($family)->setFirstName('Léa');
        $em->persist($child);

        $expense = (new Expense())->setFamily($family)->setTitle('Cantine septembre')
            ->setCategory(ExpenseCategory::Scolarite)->setAmountCents($amountCents)->setPayerMembership($payer);
        $expense->addChild($child);
        foreach ([$payer, $other] as $parent) {
            $expense->addShare((new ExpenseShare())->setMembership($parent)->setShareType(ShareType::Percent)->setValue(50));
        }
        $em->persist($expense);
        $em->flush();
    }

    public function testPageRendersBalanceAndExpense(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $family = $this->seed($client, expensesEnabled: true);
        $this->seedExpense($family);

        $client->request('GET', '/depenses');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Dépenses partagées', $html);
        self::assertStringContainsString('Cantine septembre', $html);
        // Montants formatés par ICU dans la locale de l'application, jamais en centimes bruts.
        self::assertStringContainsString('42,50', $html);
        self::assertStringContainsString('21,25', $html, 'la moitié due par chaque parent');
        self::assertStringContainsString('Bob Durand doit', $html);
        // Le statut est un TEXTE : la couleur ne porte jamais seule l'information (WCAG 1.4.1).
        self::assertStringContainsString('À régler', $html);
        // Élément de UX Turbo, seul à honorer `private` (abonnement authentifié — revue 2026-08 C2).
        self::assertStringContainsString('turbo-mercure-stream-source private', $html);
        self::assertStringContainsString('href="/depenses"', $html);
    }

    /** Module désactivé : ni onglet, ni écran — une URL en favori ne doit pas ouvrir une page vide. */
    public function testModuleDisabledHidesTabAndPage(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seed($client, expensesEnabled: false);

        $client->request('GET', '/');
        self::assertStringNotContainsString('href="/depenses"', (string) $client->getResponse()->getContent());

        $client->request('GET', '/depenses');
        self::assertResponseStatusCodeSame(404);
    }

    /** Un enfant n'a jamais accès aux dépenses, quel que soit le `Right` déclaré (cdc §2.2). */
    public function testChildSeesNeitherTabNorScreen(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seed($client, expensesEnabled: true, role: MembershipRole::Child, rights: [
            new Right(RightDomain::Expenses, RightLevel::Edit),
        ]);

        $client->request('GET', '/');
        self::assertStringNotContainsString('href="/depenses"', (string) $client->getResponse()->getContent());

        $client->request('GET', '/depenses');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("Vous n'avez pas accès aux dépenses", (string) $client->getResponse()->getContent());
    }

    /** `expenses:view` lit l'écran mais n'y trouve aucune action de saisie ni de règlement. */
    public function testViewerSeesNoWriteAction(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $family = $this->seed($client, expensesEnabled: true, role: MembershipRole::StepParent, rights: [
            new Right(RightDomain::Expenses, RightLevel::View),
        ]);
        $this->seedExpense($family);

        $client->request('GET', '/depenses');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Cantine septembre', $html);
        self::assertStringNotContainsString('create-panel__cta', $html);
        self::assertStringNotContainsString('depenses#settle', $html);
        self::assertStringNotContainsString('depenses#deleteExpense', $html);
    }

    /** Le drapeau du module se règle depuis l'onglet Famille des paramètres (US-DEP-6). */
    public function testSettingsOffersTheModuleToggleToAdmin(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seed($client, expensesEnabled: false);

        $client->request('GET', '/settings?tab=famille');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Partage des dépenses', $html);
        self::assertStringContainsString('name="expensesEnabled"', $html);
    }
}
