<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\RgpdOperation;
use App\Enum\AuditAction;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Message\RunRgpdPurge;
use App\Repository\RgpdOperationRepository;
use App\Rgpd\Purger;
use App\Security\Auth\AuditLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Worker de purge RGPD planifiée (§3.4). Exécute le Purger (live), trace une `RgpdOperation`
 * (purge) et journalise l'exécution. Idempotent : le Purger ne re-traite pas ce qui est déjà purgé.
 */
#[AsMessageHandler]
final class RunRgpdPurgeHandler
{
    public function __construct(
        private readonly Purger $purger,
        private readonly RgpdOperationRepository $operations,
        private readonly AuditLogger $audit,
    ) {
    }

    public function __invoke(RunRgpdPurge $message): void
    {
        $mode = RgpdMode::from($message->mode);
        $report = $this->purger->run($mode);

        $op = (new RgpdOperation(RgpdOperationType::Purge))
            ->setMode($mode)
            ->setStatus(RgpdOperationStatus::Done)
            ->setResultRef(\sprintf('processed=%d;skipped=%d', $report->totalProcessed(), $report->totalSkipped()));
        $this->operations->save($op);

        $this->audit->log(
            AuditAction::RgpdPurgeExecuted,
            metadata: $report->toArray(),
            entityType: 'RgpdOperation',
            entityId: (string) $op->getId(),
        );
    }
}
