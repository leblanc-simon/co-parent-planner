<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exchange;

use App\Entity\Exchange;
use App\Enum\ExchangeStatus;
use App\Service\Exchange\OverdueChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Lot 6 §3.4 — détection « en retard » : bornes fin de journée en fuseau famille, échéance nulle,
 * échange tranché, transition heure d'hiver (DST). Horloge gelée (aucune date en dur §4.1).
 */
#[CoversClass(OverdueChecker::class)]
final class OverdueCheckerTest extends TestCase
{
    private \DateTimeZone $paris;

    protected function setUp(): void
    {
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    private function checkerAt(string $nowParis): OverdueChecker
    {
        return new OverdueChecker(new MockClock(new \DateTimeImmutable($nowParis, $this->paris)));
    }

    private function exchange(?string $deadline, ExchangeStatus $status = ExchangeStatus::Open): Exchange
    {
        $e = (new Exchange())->setStatus($status);
        $e->setDeadline(null !== $deadline ? new \DateTimeImmutable($deadline) : null);

        return $e;
    }

    public function testNoDeadlineIsNeverOverdue(): void
    {
        self::assertFalse($this->checkerAt('2026-06-23 12:00')->isOverdue($this->exchange(null), $this->paris));
    }

    public function testDecidedIsNeverOverdueEvenPastDeadline(): void
    {
        $exchange = $this->exchange('2026-06-01', ExchangeStatus::Decided);
        self::assertFalse($this->checkerAt('2026-06-23 12:00')->isOverdue($exchange, $this->paris));
    }

    public function testDeadlineTodayIsNotOverdueBeforeMidnight(): void
    {
        // Échéance « aujourd'hui » : court jusqu'à 23:59:59 local → pas en retard à 23:00.
        $exchange = $this->exchange('2026-06-23');
        self::assertFalse($this->checkerAt('2026-06-23 23:00')->isOverdue($exchange, $this->paris));
    }

    public function testDeadlineYesterdayIsOverdue(): void
    {
        $exchange = $this->exchange('2026-06-22');
        self::assertTrue($this->checkerAt('2026-06-23 00:30')->isOverdue($exchange, $this->paris));
    }

    public function testOverdueRightAfterEndOfDeadlineDay(): void
    {
        $exchange = $this->exchange('2026-06-23');
        self::assertTrue($this->checkerAt('2026-06-24 00:00:30')->isOverdue($exchange, $this->paris));
    }

    public function testDstFallBackDayBoundary(): void
    {
        // 2026-10-25 : bascule heure d'hiver en Europe (jour de 25 h). Fin de journée = 23:59:59 local.
        $exchange = $this->exchange('2026-10-25');
        self::assertFalse($this->checkerAt('2026-10-25 23:30')->isOverdue($exchange, $this->paris));
        self::assertTrue($this->checkerAt('2026-10-26 00:30')->isOverdue($exchange, $this->paris));
    }
}
