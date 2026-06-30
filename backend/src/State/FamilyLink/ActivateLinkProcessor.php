<?php

declare(strict_types=1);

namespace App\State\FamilyLink;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\FamilyLink\FamilyLinkResource;
use App\Entity\FamilyLink;
use App\Enum\AuditAction;
use App\Enum\FamilyLinkStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\LinkNotFoundException;
use App\Repository\FamilyLinkRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-LINK-1 (§3.6) — la famille **destinataire** active un lien `pending` (réservé son admin).
 * Idempotent si déjà actif. Le côté initiateur ne peut pas activer sa propre proposition.
 *
 * @implements ProcessorInterface<mixed, FamilyLinkResource>
 */
final class ActivateLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly FamilyLinkRepository $links,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyLinkResource
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $link = $this->load($uriVariables['linkId'] ?? null);

        // Seul le destinataire (linkedFamily) active ; l'initiateur n'a rien à activer (anti-énum).
        if (!$link->getLinkedFamily()->getId()->equals($ctx->family->getId())) {
            throw new LinkNotFoundException();
        }

        if (FamilyLinkStatus::Pending === $link->getStatus()) {
            $link->setStatus(FamilyLinkStatus::Active);
        }

        $this->audit->log(
            AuditAction::FamilyLinkActivated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'FamilyLink',
            entityId: (string) $link->getId(),
        ); // flush

        return FamilyLinkResource::fromLink($link, $ctx->family);
    }

    private function load(mixed $id): FamilyLink
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new LinkNotFoundException();
        }
        $link = $this->links->find(Uuid::fromString($id));
        if (!$link instanceof FamilyLink) {
            throw new LinkNotFoundException();
        }

        return $link;
    }
}
