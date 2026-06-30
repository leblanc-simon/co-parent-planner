<?php

declare(strict_types=1);

namespace App\State\FamilyLink;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\FamilyLink\CreateLinkRequest;
use App\Dto\FamilyLink\FamilyLinkResource;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Enum\AuditAction;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\LinkAlreadyExistsException;
use App\Exception\LinkNotFoundException;
use App\Exception\SelfLinkForbiddenException;
use App\Repository\FamilyLinkRepository;
use App\Repository\FamilyRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-LINK-1 (§3.6) — propose un lien `pending` entre la famille active et une autre. Réservé
 * `family_settings:edit`. Anti-auto-lien, unicité bidirectionnelle, cible non énumérée.
 *
 * @implements ProcessorInterface<CreateLinkRequest, FamilyLinkResource>
 */
final class CreateLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyScope $scope,
        private readonly FamilyRepository $families,
        private readonly FamilyLinkRepository $links,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyLinkResource
    {
        \assert($data instanceof CreateLinkRequest);
        \assert(null !== $data->linkedFamilyId);

        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        if ($data->linkedFamilyId === (string) $ctx->family->getId()) {
            throw new SelfLinkForbiddenException();
        }

        $target = $this->families->find(Uuid::fromString($data->linkedFamilyId));
        if (!$target instanceof Family) {
            // Non énumérant : ne révèle pas l'existence d'autres familles.
            throw new LinkNotFoundException();
        }

        if (null !== $this->links->findByPair($ctx->family->getId(), $target->getId())) {
            throw new LinkAlreadyExistsException();
        }

        $link = new FamilyLink($ctx->family, $target, $ctx->user());
        $this->em->persist($link);

        $this->audit->log(
            AuditAction::FamilyLinkCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'FamilyLink',
            entityId: (string) $link->getId(),
        ); // flush

        return FamilyLinkResource::fromLink($link, $ctx->family);
    }
}
