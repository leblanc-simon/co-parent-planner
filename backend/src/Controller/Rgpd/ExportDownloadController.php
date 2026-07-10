<?php

declare(strict_types=1);

namespace App\Controller\Rgpd;

use App\Entity\RgpdOperation;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Exception\ExportLinkInvalidException;
use App\Exception\RgpdOperationNotFoundException;
use App\Repository\RgpdOperationRepository;
use App\Rgpd\Export\ExportSigner;
use App\Rgpd\Export\ExportStorage;
use App\Security\Auth\AuditLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Téléchargement d'un export RGPD via **lien signé temporaire** (§3.5). Double barrière :
 *  1. signature HMAC + expiration valides ({@see ExportSigner}) — sinon 403 (altéré/expiré) ;
 *  2. l'utilisateur authentifié EST le sujet de l'export — sinon 403 (jamais d'export d'autrui, §2.3).
 */
final class ExportDownloadController
{
    public function __construct(
        private readonly ExportSigner $signer,
        private readonly RgpdOperationRepository $operations,
        private readonly ExportStorage $storage,
        private readonly Security $security,
        private readonly AuditLogger $audit,
    ) {
    }

    #[Route('/api/me/export/{id}/download', name: 'api_me_export_download', methods: ['GET'])]
    public function __invoke(string $id, Request $request): Response
    {
        if (!$this->signer->isRequestValid($request)) {
            throw new ExportLinkInvalidException();
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || !Uuid::isValid($id)) {
            throw new ExportLinkInvalidException();
        }

        $operation = $this->operations->find(Uuid::fromString($id));
        if (!$operation instanceof RgpdOperation
            || RgpdOperationType::Export !== $operation->getType()
            || RgpdOperationStatus::Done !== $operation->getStatus()
            || null === $operation->getResultRef()) {
            throw new RgpdOperationNotFoundException();
        }

        // Scope strict au sujet : un tiers (authentifié comme lui-même) ne peut pas récupérer l'archive.
        if (true !== $operation->getSubjectUser()?->getId()->equals($user->getId())) {
            throw new ExportLinkInvalidException();
        }

        $bytes = $this->storage->read($operation->getResultRef());

        $this->audit->log(
            AuditAction::RgpdExportDownloaded,
            $user,
            entityType: 'RgpdOperation',
            entityId: (string) $operation->getId(),
        );

        return new Response($bytes, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="coparentplanner-export.zip"',
            'X-Content-Type-Options' => 'nosniff',
            // Archive de données personnelles : jamais stockée par un cache (disque navigateur inclus).
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
