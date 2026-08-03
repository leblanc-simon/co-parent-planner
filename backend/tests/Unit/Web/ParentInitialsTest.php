<?php

declare(strict_types=1);

namespace App\Tests\Unit\Web;

use App\Web\ParentInitials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Jumeau de `mobile/test/features/custody/parent_initials_test.dart` : **les mêmes cas doivent
 * figurer des deux côtés** (cdc §8 — la règle est normative et implémentée dans les deux clients).
 */
final class ParentInitialsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function pairs(): iterable
    {
        yield 'prénoms distincts' => ['Sophie Martin', 'Marc Durand', 'S', 'M'];
        yield 'collision sur la première lettre' => ['Sophie', 'Simon', 'So', 'Si'];
        yield 'collision sur deux lettres' => ['Sophie', 'Sophia', 'A', 'B'];
        yield 'accent conservé et distinctif' => ['Élodie', 'Eric', 'É', 'E'];
        yield 'casse normalisée' => ['sophie martin', 'MARC', 'S', 'M'];
        yield 'repli e-mail' => ['sophie.martin@example.test', 'marc@example.test', 'S', 'M'];
        yield 'repli e-mail en collision' => ['sophie@example.test', 'simon@example.test', 'So', 'Si'];
        yield 'homonymes stricts' => ['Camille', 'Camille', 'A', 'B'];
        // Plafond de deux lettres : ces deux e-mails ne divergent qu'au 4ᵉ caractère. Un marqueur
        // « Web »/« Web2 » ne tient pas dans une case de calendrier — repli A/B attendu.
        yield 'préfixe commun au-delà de deux lettres' => ['web@example.com', 'web2@example.com', 'A', 'B'];
        yield 'nom vide' => ['', 'Marc', 'A', 'B'];
        yield 'espaces seuls' => ['   ', '   ', 'A', 'B'];
    }

    #[DataProvider('pairs')]
    public function testPair(string $nameA, string $nameB, string $expectedA, string $expectedB): void
    {
        self::assertSame(
            ['A' => $expectedA, 'B' => $expectedB],
            (new ParentInitials())->pair($nameA, $nameB),
        );
    }

    /**
     * L'invariant qui porte l'accessibilité : quels que soient les noms, les deux marqueurs
     * diffèrent — sinon la case de calendrier redevient muette sans la couleur.
     */
    #[DataProvider('pairs')]
    public function testMarkersAlwaysDiffer(string $nameA, string $nameB, string $ignoredA, string $ignoredB): void
    {
        $pair = (new ParentInitials())->pair($nameA, $nameB);

        self::assertNotSame($pair['A'], $pair['B']);
        self::assertNotSame('', $pair['A']);
        self::assertNotSame('', $pair['B']);
    }
}
