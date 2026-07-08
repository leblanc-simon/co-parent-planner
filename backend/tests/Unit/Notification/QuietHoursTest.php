<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\User;
use App\Service\Notification\QuietHours;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Lot 7 §3.3 — report quiet-hours : plage simple, plage circulaire (passe minuit), hors plage, plage
 * vide. La cible du report est la fin de plage en heure locale (fuseau famille).
 */
#[CoversClass(QuietHours::class)]
final class QuietHoursTest extends TestCase
{
    private QuietHours $qh;
    private \DateTimeZone $paris;

    protected function setUp(): void
    {
        $this->qh = new QuietHours();
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    private function user(?string $start, ?string $end): User
    {
        return (new User())->setQuietHoursStart($start)->setQuietHoursEnd($end);
    }

    private function at(string $parisLocal): \DateTimeImmutable
    {
        return new \DateTimeImmutable($parisLocal, $this->paris);
    }

    public function testNoQuietHoursNoDefer(): void
    {
        self::assertNull($this->qh->deferUntil($this->user(null, null), $this->at('2026-06-23 23:15'), $this->paris));
    }

    public function testEmptyRangeNoDefer(): void
    {
        self::assertNull($this->qh->deferUntil($this->user('00:00', '00:00'), $this->at('2026-06-23 03:00'), $this->paris));
    }

    public function testCircularRangeEveningDefersToNextMorning(): void
    {
        $target = $this->qh->deferUntil($this->user('22:00', '07:00'), $this->at('2026-06-23 23:15'), $this->paris);
        self::assertNotNull($target);
        self::assertSame('2026-06-24 07:00', $target->setTimezone($this->paris)->format('Y-m-d H:i'));
    }

    public function testCircularRangeAfterMidnightDefersToSameMorning(): void
    {
        $target = $this->qh->deferUntil($this->user('22:00', '07:00'), $this->at('2026-06-23 01:00'), $this->paris);
        self::assertNotNull($target);
        self::assertSame('2026-06-23 07:00', $target->setTimezone($this->paris)->format('Y-m-d H:i'));
    }

    public function testOutsideCircularRangeNoDefer(): void
    {
        self::assertNull($this->qh->deferUntil($this->user('22:00', '07:00'), $this->at('2026-06-23 12:00'), $this->paris));
    }

    public function testSameDayRange(): void
    {
        $user = $this->user('01:00', '06:00');
        $target = $this->qh->deferUntil($user, $this->at('2026-06-23 03:00'), $this->paris);
        self::assertNotNull($target);
        self::assertSame('2026-06-23 06:00', $target->setTimezone($this->paris)->format('Y-m-d H:i'));
        self::assertNull($this->qh->deferUntil($user, $this->at('2026-06-23 08:00'), $this->paris));
    }
}
