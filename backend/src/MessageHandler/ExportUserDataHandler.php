<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\RgpdOperation;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\RgpdOperationStatus;
use App\Message\ExportUserData;
use App\Repository\RgpdOperationRepository;
use App\Repository\UserRepository;
use App\Rgpd\Export\ExportArchiver;
use App\Rgpd\Export\ExportSigner;
use App\Rgpd\Export\ExportStorage;
use App\Rgpd\Export\UserDataExporter;
use App\Security\Auth\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Worker d'assemblage d'un export RGPD (§3.5). Idempotent sur le statut de la `RgpdOperation`
 * (Done → no-op). Produit l'archive, la stocke (stockage objet UE en prod), renseigne `resultRef`,
 * journalise. En cas d'échec de stockage, l'opération passe `failed` et l'exception est relancée
 * pour laisser Messenger réessayer (aucun lien partiel servi, §4.6).
 */
#[AsMessageHandler]
final class ExportUserDataHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RgpdOperationRepository $operations,
        private readonly UserRepository $users,
        private readonly UserDataExporter $exporter,
        private readonly ExportArchiver $archiver,
        private readonly ExportStorage $storage,
        private readonly ExportSigner $signer,
        private readonly AuditLogger $audit,
    ) {
    }

    public function __invoke(ExportUserData $message): void
    {
        $operation = $this->operations->find(Uuid::fromString($message->operationId));
        if (!$operation instanceof RgpdOperation || RgpdOperationStatus::Done === $operation->getStatus()) {
            return; // opération inconnue ou déjà finalisée (idempotence séquentielle)
        }

        // Réclamation atomique pending|failed → running : un second worker concurrent (redélivrance
        // at-least-once) obtient false et abandonne, évitant double archive + double audit.
        if (!$this->operations->claimForProcessing($operation->getId())) {
            return;
        }
        $this->em->refresh($operation); // resynchronise l'état en mémoire (running)

        $user = $this->users->find(Uuid::fromString($message->subjectUserId));
        if (!$user instanceof User) {
            $operation->setStatus(RgpdOperationStatus::Failed);
            $this->em->flush();

            return; // sujet disparu → rien à exporter (ACK)
        }

        try {
            $archive = $this->archiver->archive($this->exporter->build($user));
            $key = $this->signer->storageKey($operation);
            $this->storage->store($key, $archive);

            $operation->setResultRef($key)->setStatus(RgpdOperationStatus::Done);
            $this->audit->persist(
                AuditAction::RgpdExportCompleted,
                $user,
                metadata: ['operation' => (string) $operation->getId(), 'bytes' => \strlen($archive)],
                entityType: 'RgpdOperation',
                entityId: (string) $operation->getId(),
            );
            $this->em->flush();
        } catch (\Throwable $e) {
            $operation->setStatus(RgpdOperationStatus::Failed);
            $this->audit->persist(
                AuditAction::RgpdExportFailed,
                $user,
                metadata: ['operation' => (string) $operation->getId(), 'error' => $e::class],
                entityType: 'RgpdOperation',
                entityId: (string) $operation->getId(),
            );
            $this->em->flush();

            throw $e; // laisse Messenger réessayer (retry borné)
        }
    }
}
