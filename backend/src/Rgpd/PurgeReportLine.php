<?php

declare(strict_types=1);

namespace App\Rgpd;

/**
 * Une ligne du rapport de purge : ce qui a été (ou serait, en dry-run) traité pour un scope de rétention.
 */
final readonly class PurgeReportLine
{
    public function __construct(
        public string $scope,
        public string $label,
        public string $action,
        public int $durationDays,
        public int $candidates,
        public int $processed,
        public int $skipped,
    ) {
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'label' => $this->label,
            'action' => $this->action,
            'durationDays' => $this->durationDays,
            'candidates' => $this->candidates,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
        ];
    }
}
