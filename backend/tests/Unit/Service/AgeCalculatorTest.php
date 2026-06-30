<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Child\AgeCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Calcul d'âge (§3.1) : bornes anniversaire, 29/02, date future, null — horloge mockée.
 */
final class AgeCalculatorTest extends TestCase
{
    private function calculator(string $now): AgeCalculator
    {
        return new AgeCalculator(new MockClock(new \DateTimeImmutable($now, new \DateTimeZone('Europe/Paris'))));
    }

    private function date(string $d): \DateTimeImmutable
    {
        return new \DateTimeImmutable($d);
    }

    public function testNullBirthDateReturnsNull(): void
    {
        self::assertNull($this->calculator('2026-06-23 10:00')->age(null));
    }

    public function testFutureBirthDateReturnsNull(): void
    {
        self::assertNull($this->calculator('2026-06-23 10:00')->age($this->date('2030-01-01')));
    }

    public function testBirthdayBoundaries(): void
    {
        // Né le 23/06/2016 : la veille 7 ans, le jour 10 ans le 2026… vérifions J-1/J/J+1.
        $birth = $this->date('2016-06-23');
        self::assertSame(9, $this->calculator('2026-06-22 12:00')->age($birth), 'veille de l\'anniversaire');
        self::assertSame(10, $this->calculator('2026-06-23 12:00')->age($birth), 'jour de l\'anniversaire');
        self::assertSame(10, $this->calculator('2026-06-24 12:00')->age($birth), 'lendemain');
    }

    public function testLeapYearBirthday(): void
    {
        $birth = $this->date('2016-02-29');
        // Année non bissextile : l'âge augmente le 1er mars (pas le 28/02).
        self::assertSame(8, $this->calculator('2025-02-28 12:00')->age($birth));
        self::assertSame(9, $this->calculator('2025-03-01 12:00')->age($birth));
    }
}
