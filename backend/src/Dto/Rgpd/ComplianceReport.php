<?php

declare(strict_types=1);

namespace App\Dto\Rgpd;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Rgpd\ComplianceReportProvider;

/**
 * Rapport d'état de conformité (US-RGPD-4) : rétentions en vigueur, écarts détectés, hébergement UE.
 * `GET /admin/rgpd/compliance-report` (admin technique + step-up).
 */
#[ApiResource(
    shortName: 'RgpdComplianceReport',
    operations: [
        new Get(
            uriTemplate: '/admin/rgpd/compliance-report',
            provider: ComplianceReportProvider::class,
        ),
    ],
)]
class ComplianceReport
{
    /** @var list<array<string, mixed>> */
    public array $retentionRules = [];
    /** @var list<string> écarts (ex. champ chiffré sans entrée de registre) */
    public array $gaps = [];
    public int $personalFields = 0;
    public bool $euHosting = true;
    public string $generatedAt = '';
}
