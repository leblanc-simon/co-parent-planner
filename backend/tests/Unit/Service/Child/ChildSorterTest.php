<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Child;

use App\Entity\Child;
use App\Service\Child\ChildSorter;
use PHPUnit\Framework\TestCase;

/**
 * Le tri des enfants a quitté SQL le jour où `first_name` est passé chiffré : ces cas sont ce que
 * `ORDER BY first_name` rendait auparavant, et qu'il ne rend plus.
 */
final class ChildSorterTest extends TestCase
{
    public function testSortsAlphabetically(): void
    {
        $sorted = ChildSorter::byFirstName([
            self::child('Théo'),
            self::child('Ambre'),
            self::child('Léa'),
        ]);

        self::assertSame(['Ambre', 'Léa', 'Théo'], self::names($sorted));
    }

    /** « Élise » se range avec les E : un tri d'octets l'aurait rejetée après « Zoé ». */
    public function testAccentsSortWithTheirBaseLetter(): void
    {
        $sorted = ChildSorter::byFirstName([
            self::child('Zoé'),
            self::child('Élise'),
            self::child('Emma'),
        ]);

        self::assertSame(['Élise', 'Emma', 'Zoé'], self::names($sorted));
    }

    public function testIsCaseInsensitive(): void
    {
        $sorted = ChildSorter::byFirstName([
            self::child('bob'),
            self::child('Alice'),
        ]);

        self::assertSame(['Alice', 'bob'], self::names($sorted));
    }

    /**
     * Deux homonymes doivent sortir dans le même ordre à chaque appel : sans départage par
     * identifiant, ils suivraient l'ordre de lecture SQL, qu'aucune requête ne garantit.
     */
    public function testHomonymsAreOrderedDeterministically(): void
    {
        $first = self::child('Léa');
        $second = self::child('Léa');

        $ascending = ChildSorter::byFirstName([$first, $second]);
        $descending = ChildSorter::byFirstName([$second, $first]);

        self::assertSame(
            array_map(static fn (Child $c): string => (string) $c->getId(), $ascending),
            array_map(static fn (Child $c): string => (string) $c->getId(), $descending),
        );
    }

    public function testEmptyListStaysEmpty(): void
    {
        self::assertSame([], ChildSorter::byFirstName([]));
    }

    private static function child(string $firstName): Child
    {
        return (new Child())->setFirstName($firstName);
    }

    /**
     * @param list<Child> $children
     *
     * @return list<string>
     */
    private static function names(array $children): array
    {
        return array_map(static fn (Child $c): string => $c->getFirstName(), $children);
    }
}
