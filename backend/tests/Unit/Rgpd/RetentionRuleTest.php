<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rgpd;

use App\Rgpd\Retention\RetentionAction;
use App\Rgpd\Retention\RetentionRule;
use App\Rgpd\Retention\RetentionScope;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * La borne d'expiration se dérive de `now()` (Europe/Paris), jamais d'une date codée en dur (§4.1).
 */
final class RetentionRuleTest extends TestCase
{
    public function testExpiresBeforeSubtractsDurationFromNow(): void
    {
        $rule = new RetentionRule(RetentionScope::AuditLogs, 30, RetentionAction::Delete, true, 'Journaux');
        $clock = new MockClock(new \DateTimeImmutable('2026-07-10 12:00:00', new \DateTimeZone('UTC')));

        $threshold = $rule->expiresBefore($clock);

        // 30 jours civils avant le 10 juillet 2026 = 10 juin 2026 ; borne rendue en UTC (alignée sur
        // les colonnes datetime stockées en heure murale UTC).
        self::assertSame('2026-06-10', $threshold->format('Y-m-d'));
        self::assertSame('UTC', $threshold->getTimezone()->getName());
    }

    public function testDurationSpanningDstStaysCalendarCorrect(): void
    {
        // Fenêtre traversant le passage heure d'hiver→été (dernier dimanche de mars) : les jours civils
        // restent corrects malgré le saut horaire.
        $rule = new RetentionRule(RetentionScope::AuditLogs, 10, RetentionAction::Delete, true, 'Journaux');
        $clock = new MockClock(new \DateTimeImmutable('2026-04-02 09:00:00', new \DateTimeZone('UTC')));

        self::assertSame('2026-03-23', $rule->expiresBefore($clock)->format('Y-m-d'));
    }
}
