<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Exception\ChildDeletionBlockedException;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-5 — supprime un profil enfant (droit `CHILD_MANAGE`). Refusé si rattaché à un compte-enfant
 * (garde-fou §4.2). Cascade sur les notes.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteChildProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChildAccess $access,
        private readonly MembershipRepository $memberships,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['id'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::ChildManage);

        if ($this->memberships->existsForChild($child->getId())) {
            throw new ChildDeletionBlockedException();
        }

        $childId = (string) $child->getId();
        $this->em->remove($child);

        $this->audit->log(
            AuditAction::ChildDeleted,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Child',
            entityId: $childId,
        ); // flush

        return null;
    }
}
