<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use PHPUnit\Framework\TestCase;

/**
 * AuditLog est strictement append-only (cdc §5.4, US-RGPD-5) : les callbacks PreUpdate/PreRemove
 * lèvent une exception, garantissant l'immuabilité au niveau applicatif.
 */
final class AuditLogAppendOnlyTest extends TestCase
{
    public function testUpdateIsForbidden(): void
    {
        $log = new AuditLog(AuditAction::RgpdPurgeExecuted);

        $this->expectException(\LogicException::class);
        $log->preventUpdate();
    }

    public function testDeleteIsForbidden(): void
    {
        $log = new AuditLog(AuditAction::RgpdErasureCompleted);

        $this->expectException(\LogicException::class);
        $log->preventDelete();
    }
}
