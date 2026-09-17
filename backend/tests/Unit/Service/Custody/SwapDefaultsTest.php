<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Custody;

use App\Service\Custody\SwapDefaults;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Créneau proposé par « Échanger ce jour » (cdc §3.4) — les deux exemples de la demande du
 * 2026-09-11, avec un échange à 18:00 et une journée qui commence à 08:00.
 */
#[CoversClass(SwapDefaults::class)]
final class SwapDefaultsTest extends TestCase
{
    private const string A = 'aaaaaaaa-0000-4000-8000-000000000001';
    private const string B = 'bbbbbbbb-0000-4000-8000-000000000002';

    /**
     * « A -> 25 | B -> 26, 27, 28 », le 26 pour A : la veille est déjà à A, l'échange **prolonge**
     * sa garde depuis la transmission du 25.
     */
    public function testSwapExtendsFromYesterdayHandoverWhenTheParentAlreadyHadTheChildren(): void
    {
        $defaults = SwapDefaults::forDay(new \DateTimeImmutable('2026-09-26'), self::A, self::A, '18:00', '08:00');

        self::assertSame('2026-09-25', $defaults->startDate);
        self::assertSame('18:00', $defaults->startTime);
        self::assertSame('2026-09-26', $defaults->endDate);
        self::assertSame('18:00', $defaults->endTime);
    }

    /**
     * « A -> 25, 29 | B -> 26, 27, 28 », le 28 pour A : la veille est à B, il n'y a rien à
     * prolonger — A vient chercher les enfants le matin même.
     */
    public function testSwapStartsInTheMorningWhenYesterdayBelongsToTheOtherParent(): void
    {
        $defaults = SwapDefaults::forDay(new \DateTimeImmutable('2026-09-28'), self::B, self::A, '18:00', '08:00');

        self::assertSame('2026-09-28', $defaults->startDate);
        self::assertSame('08:00', $defaults->startTime);
        self::assertSame('2026-09-28', $defaults->endDate);
        self::assertSame('18:00', $defaults->endTime);
    }

    /** Veille non matérialisée (bout d'horizon) : rien à prolonger, on reprend le matin. */
    public function testSwapStartsInTheMorningWhenYesterdayIsUnknown(): void
    {
        $defaults = SwapDefaults::forDay(new \DateTimeImmutable('2026-09-28'), null, self::A, '19:00', '08:00');

        self::assertSame('2026-09-28', $defaults->startDate);
        self::assertSame('08:00', $defaults->startTime);
        self::assertSame('19:00', $defaults->endTime);
    }

    /**
     * Réglage inhabituel mais permis — journée qui « commence » après l'heure d'échange : la fin
     * proposée bascule au lendemain, sinon le créneau serait vide et l'échange sans effet.
     */
    public function testSwapEndsNextDayWhenTheDayStartsAfterTheHandover(): void
    {
        $defaults = SwapDefaults::forDay(new \DateTimeImmutable('2026-09-28'), self::B, self::A, '07:00', '08:00');

        self::assertSame('2026-09-28', $defaults->startDate);
        self::assertSame('08:00', $defaults->startTime);
        self::assertSame('2026-09-29', $defaults->endDate);
        self::assertSame('07:00', $defaults->endTime);
    }
}
