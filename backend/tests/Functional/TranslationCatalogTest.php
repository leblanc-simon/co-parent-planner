<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\Auth\PasswordPolicy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Garde-fous de la chaîne i18n (cdc §4.6 : seule fr-FR est livrée).
 *
 * 1. Le catalogue fr-FR doit se **parser** intégralement. Les tests fonctionnels ne consomment pas
 *    le handler d'e-mail async (seul chemin qui chargeait le catalogue), si bien qu'un YAML invalide
 *    passait inaperçu jusqu'à l'E2E — ce test le rattrape au niveau unitaire.
 * 2. La locale par défaut de l'application doit être `fr`. Elle valait `en` (redéclaration dans
 *    translation.yaml, chargé après framework.yaml), et comme aucun catalogue `en` n'existe, tout
 *    `|trans` sans locale explicite renvoyait sa clé brute : le bandeau anti-données-de-santé de
 *    l'écran Enfants affichait « child.health_warning », et les réponses RFC 7807 sortaient avec
 *    `"title": "problem.title.unprocessable"`.
 * 3. Les messages de contrainte `#[Assert\...]` sont traduits dans le domaine **`validators`**, pas
 *    `messages` : chaque clé doit exister dans les DEUX catalogues, avec le même libellé.
 */
final class TranslationCatalogTest extends KernelTestCase
{
    /**
     * Clés utilisées comme `message:` d'un attribut `#[Assert\...]`.
     * Toute nouvelle contrainte à clé de traduction doit être ajoutée ici.
     */
    private const array CONSTRAINT_KEYS = [
        'child.error.first_name_required',
        'note.error.text_required',
        // Écrans d'auth et création de famille (revue 2026-08 C12).
        'auth.error.email_required',
        'auth.error.email_invalid',
        'auth.error.email_too_long',
        'auth.error.password_required',
        'auth.error.password_too_short',
        'auth.error.password_too_long',
        'auth.error.link_incomplete',
        'family.error.name_required',
        'family.error.name_too_long',
    ];

    public function testFrenchCatalogParsesAndResolvesKeys(): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        // Forcer le chargement/parse du catalogue fr (lève si YAML invalide).
        foreach ([
            'agenda.error.recurrence_too_long',
            'agenda.error.not_found',
            'custody.error.not_found',
        ] as $key) {
            $message = $translator->trans($key, [], 'messages', 'fr');
            self::assertNotSame($key, $message, "Clé de traduction non résolue : {$key}");
        }
    }

    public function testDefaultLocaleIsFrench(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertSame(
            'fr',
            $container->getParameter('kernel.default_locale'),
            'La locale par défaut doit être fr : sans catalogue `en`, toute traduction sans locale '
            .'explicite renvoie sa clé brute à l\'écran.',
        );
    }

    /**
     * Le bandeau anti-données-de-santé est un invariant produit (aucun champ typé santé, avertissement
     * persistant sur l'écran d'édition enfant) : il est rendu par le seul `|trans` de l'UI web, donc
     * sans locale explicite. On vérifie qu'il se résout dans la locale par défaut.
     */
    public function testHealthWarningResolvesInDefaultLocale(): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        $message = $translator->trans('child.health_warning');
        self::assertNotSame('child.health_warning', $message);
        self::assertStringContainsString('santé', $message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function constraintKeyProvider(): iterable
    {
        foreach (self::CONSTRAINT_KEYS as $key) {
            yield $key => [$key];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('constraintKeyProvider')]
    public function testConstraintKeysResolveInValidatorsDomain(string $key): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        $inValidators = $translator->trans($key, [], 'validators', 'fr');
        self::assertNotSame(
            $key,
            $inValidators,
            "La contrainte {$key} sort en clé brute dans les violations de l'API : ajoutez-la à "
            .'translations/validators+intl-icu.fr.yaml.',
        );

        // Même libellé de part et d'autre : le doublon de catalogue est assumé, la divergence non.
        self::assertSame(
            $translator->trans($key, [], 'messages', 'fr'),
            $inValidators,
            "Le libellé de {$key} diverge entre les domaines `messages` et `validators`.",
        );
    }

    /**
     * Le message « mot de passe trop court » cite la longueur minimale : elle doit rester alignée sur
     * {@see PasswordPolicy::MIN_LENGTH}, sans quoi l'écran annoncerait une règle que l'API n'applique
     * pas. (Un placeholder du validateur n'est pas utilisable ici : le catalogue est au format ICU,
     * qui ne connaît pas la syntaxe `{{ limit }}` des contraintes.).
     */
    public function testPasswordMessageMatchesPolicy(): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertStringContainsString(
            (string) PasswordPolicy::MIN_LENGTH,
            $translator->trans('auth.error.password_too_short', [], 'validators', 'fr'),
        );
        self::assertStringContainsString(
            (string) PasswordPolicy::MAX_LENGTH,
            $translator->trans('auth.error.password_too_long', [], 'validators', 'fr'),
        );
    }

    /**
     * Les messages de contrainte natifs de Symfony (« This value should not be blank. ») doivent eux
     * aussi ressortir en français : c'est le cas dès que la locale par défaut est `fr`.
     */
    public function testBuiltInConstraintMessagesAreFrench(): void
    {
        self::bootKernel();
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        $message = $translator->trans('This value should not be blank.', [], 'validators');
        self::assertSame('Cette valeur ne doit pas être vide.', $message);
    }
}
