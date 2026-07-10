<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Rgpd\PurgeResult;
use App\Entity\RgpdOperation;
use App\Enum\AuditAction;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Repository\RgpdOperationRepository;
use App\Rgpd\Purger;
use App\Security\Admin\AdminAccess;
use App\Security\Auth\AuditLogger;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-RGPD-3 — déclenche une purge RGPD (admin technique + step-up). **Dry-run par défaut** :
 * `?dryRun=false` pour exécuter réellement. Idempotente, tracée (RgpdOperation + AuditLog).
 *
 * @implements ProcessorInterface<mixed, PurgeResult>
 */
final class PurgeProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AdminAccess $admin,
        private readonly Purger $purger,
        private readonly RgpdOperationRepository $operations,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PurgeResult
    {
        $admin = $this->admin->require();

        // Dry-run par défaut : seul `dryRun=false` déclenche une purge réelle.
        $dryRun = 'false' !== $this->requestStack->getCurrentRequest()?->query->get('dryRun', 'true');
        $mode = $dryRun ? RgpdMode::DryRun : RgpdMode::Live;

        $report = $this->purger->run($mode);

        $op = (new RgpdOperation(RgpdOperationType::Purge))
            ->setMode($mode)
            ->setStatus(RgpdOperationStatus::Done)
            ->setRequestedBy($admin)
            ->setResultRef(\sprintf('processed=%d;skipped=%d', $report->totalProcessed(), $report->totalSkipped()));
        $this->operations->save($op);

        $this->audit->log(
            AuditAction::RgpdPurgeExecuted,
            $admin,
            metadata: ['mode' => $mode->value] + $report->toArray(),
            entityType: 'RgpdOperation',
            entityId: (string) $op->getId(),
        );

        $array = $report->toArray();

        /** @var array<string, int> $totals */
        $totals = $array['totals'];
        /** @var list<array<string, string|int>> $rules */
        $rules = $array['rules'];

        return new PurgeResult($mode->value, $totals, $rules);
    }
}
