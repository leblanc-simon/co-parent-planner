<?php

declare(strict_types=1);

namespace App\Rgpd;

/**
 * Résultat d'une exécution de purge (dry-run ou live) : par règle, le nombre de cibles et l'issue.
 */
final readonly class PurgeReport
{
    /**
     * @param list<PurgeReportLine> $lines
     */
    public function __construct(
        public string $mode,
        public array $lines,
    ) {
    }

    public function totalCandidates(): int
    {
        return array_sum(array_map(static fn (PurgeReportLine $l): int => $l->candidates, $this->lines));
    }

    public function totalProcessed(): int
    {
        return array_sum(array_map(static fn (PurgeReportLine $l): int => $l->processed, $this->lines));
    }

    public function totalSkipped(): int
    {
        return array_sum(array_map(static fn (PurgeReportLine $l): int => $l->skipped, $this->lines));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'totals' => [
                'candidates' => $this->totalCandidates(),
                'processed' => $this->totalProcessed(),
                'skipped' => $this->totalSkipped(),
            ],
            'rules' => array_map(static fn (PurgeReportLine $l): array => $l->toArray(), $this->lines),
        ];
    }
}
