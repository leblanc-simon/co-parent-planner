<?php

declare(strict_types=1);

namespace App\State\FamilyLink;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Enum\AuditAction;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\LinkNotFoundException;
use App\Repository\FamilyLinkRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-LINK-2 (§3.6) — délie deux familles (réservé l'admin de l'une des deux). Effet bidirectionnel
 * (un seul enregistrement supprimé).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class RemoveLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyScope $scope,
        private readonly FamilyLinkRepository $links,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $link = $this->load($uriVariables['linkId'] ?? null, $ctx->family);
        $linkId = (string) $link->getId();

        $this->em->remove($link);

        $this->audit->log(
            AuditAction::FamilyLinkRemoved,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'FamilyLink',
            entityId: $linkId,
        ); // flush

        return null;
    }

    private function load(mixed $id, Family $family): FamilyLink
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new LinkNotFoundException();
        }
        $link = $this->links->find(Uuid::fromString($id));
        // Le lien doit impliquer la famille active (dans un sens ou l'autre).
        $involves = $link instanceof FamilyLink
            && ($link->getFamily()->getId()->equals($family->getId()) || $link->getLinkedFamily()->getId()->equals($family->getId()));
        if (!$involves) {
            throw new LinkNotFoundException();
        }
        \assert($link instanceof FamilyLink);

        return $link;
    }
}
