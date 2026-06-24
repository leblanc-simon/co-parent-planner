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
}
