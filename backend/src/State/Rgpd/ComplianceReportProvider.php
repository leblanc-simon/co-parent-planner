<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rgpd\ComplianceReport;
use App\Enum\AuditAction;
use App\Rgpd\ComplianceReporter;
use App\Security\Admin\AdminAccess;
use App\Security\Auth\AuditLogger;

/**
 * US-RGPD-4 — rapport d'état de conformité (admin technique + step-up).
 *
 * @implements ProviderInterface<ComplianceReport>
 */
final class ComplianceReportProvider implements ProviderInterface
{
    public function __construct(
        private readonly AdminAccess $admin,
        private readonly ComplianceReporter $reporter,
        private readonly AuditLogger $audit,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ComplianceReport
    {
        $admin = $this->admin->require();
        $this->audit->log(AuditAction::RgpdComplianceReportViewed, $admin);

        return $this->reporter->complianceReport();
    }
}
