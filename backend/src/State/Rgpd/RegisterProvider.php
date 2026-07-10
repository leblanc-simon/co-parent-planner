<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rgpd\RegisterEntry;
use App\Enum\AuditAction;
use App\Rgpd\ComplianceReporter;
use App\Security\Admin\AdminAccess;
use App\Security\Auth\AuditLogger;

/**
 * US-RGPD-1 — registre des traitements (admin technique + step-up). Aligné sur les attributs
 * `#[PersonalData]` et `registre-donnees.md`.
 *
 * @implements ProviderInterface<RegisterEntry>
 */
final class RegisterProvider implements ProviderInterface
{
    public function __construct(
        private readonly AdminAccess $admin,
        private readonly ComplianceReporter $reporter,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return list<RegisterEntry>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $admin = $this->admin->require();
        $this->audit->log(AuditAction::RgpdRegisterViewed, $admin);

        return $this->reporter->register();
    }
}
