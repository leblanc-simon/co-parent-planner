<?php

declare(strict_types=1);

namespace App\Rgpd;

use App\Dto\Rgpd\ComplianceReport;
use App\Dto\Rgpd\RegisterEntry;
use App\Rgpd\Retention\RetentionPolicy;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Génère les rapports du RGPD Manager (US-RGPD-1 / US-RGPD-4) : registre des traitements (aligné sur
 * les attributs `#[PersonalData]`) et rapport de conformité (rétentions en vigueur, écarts, hébergement UE).
 */
final class ComplianceReporter
{
    public function __construct(
        private readonly PersonalDataRegistry $registry,
        private readonly RetentionPolicy $policy,
        private readonly ClockInterface $clock,
        #[Autowire('%env(bool:RGPD_EU_HOSTING)%')]
        private readonly bool $euHosting = true,
    ) {
    }

    /**
     * @return list<RegisterEntry>
     */
    public function register(): array
    {
        $entries = [];
        foreach ($this->registry->collect() as $entry) {
            $view = new RegisterEntry();
            $view->entity = $entry->entityShortName;
            $view->field = $entry->field;
            $view->category = $entry->meta->category->value;
            $view->encrypted = $entry->meta->encrypted;
            $view->legalBasis = $entry->meta->legalBasis;
            $view->purpose = $entry->meta->purpose;
            $view->retention = $entry->meta->retention;
            $view->recipients = $entry->meta->recipients;
            $entries[] = $view;
        }

        return $entries;
    }

    public function complianceReport(): ComplianceReport
    {
        $report = new ComplianceReport();

        foreach ($this->policy->rules() as $rule) {
            $report->retentionRules[] = [
                'scope' => $rule->scope->value,
                'label' => $rule->label,
                'action' => $rule->action->value,
                'durationDays' => $rule->durationDays,
                'enabled' => $rule->enabled,
            ];
        }

        // Écart de conformité : un champ chiffré 🔒 sans entrée de registre = fuite potentielle.
        foreach ($this->registry->encryptedFieldsMissingPersonalData() as $missing) {
            $report->gaps[] = \sprintf('Champ chiffré sans entrée de registre : %s', $missing);
        }

        $report->personalFields = \count($this->registry->collect());
        $report->euHosting = $this->euHosting;
        $report->generatedAt = $this->clock->now()->format(\DateTimeInterface::ATOM);

        return $report;
    }
}
