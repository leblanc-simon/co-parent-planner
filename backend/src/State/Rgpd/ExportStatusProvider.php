<?php

declare(strict_types=1);

namespace App\State\Rgpd;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Rgpd\ExportResource;
use App\Entity\RgpdOperation;
use App\Entity\User;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Exception\RgpdOperationNotFoundException;
use App\Repository\RgpdOperationRepository;
use App\Rgpd\Export\ExportSigner;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * Statut d'un export RGPD du sujet (§3.5). 404 uniforme si l'opération n'existe pas ou n'appartient
 * pas au sujet (jamais d'export d'autrui). Une fois `done`, expose un lien signé temporaire frais.
 *
 * @implements ProviderInterface<ExportResource>
 */
final class ExportStatusProvider implements ProviderInterface
{
    public function __construct(
        private readonly RgpdOperationRepository $operations,
        private readonly Security $security,
        private readonly ExportSigner $signer,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ExportResource
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new RgpdOperationNotFoundException();
        }

        $op = $this->operations->find(Uuid::fromString($id));
        if (!$op instanceof RgpdOperation
            || RgpdOperationType::Export !== $op->getType()
            || true !== $op->getSubjectUser()?->getId()->equals($user->getId())) {
            throw new RgpdOperationNotFoundException();
        }

        $downloadUrl = (RgpdOperationStatus::Done === $op->getStatus() && null !== $op->getResultRef())
            ? $this->signer->signedUrl($op)
            : null;

        return new ExportResource((string) $op->getId(), $op->getStatus()->value, $downloadUrl);
    }
}
