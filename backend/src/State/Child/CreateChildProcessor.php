<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Child\ChildResource;
use App\Dto\Child\CreateChildRequest;
use App\Entity\Child;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use App\Service\Child\AgeCalculator;
use App\Service\Child\ChildWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-4 — crée un profil enfant dans la famille active (droit `CHILD_MANAGE`, jamais un `child`).
 *
 * @implements ProcessorInterface<CreateChildRequest, ChildResource>
 */
final class CreateChildProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChildAccess $access,
        private readonly ChildWriter $writer,
        private readonly AgeCalculator $age,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChildResource
    {
        \assert($data instanceof CreateChildRequest);

        $ctx = $this->access->context();
        $this->access->authorize($ctx, ChildNotesAction::ChildManage);

        $child = (new Child())->setFamily($ctx->family);
        $this->writer->applyCreate($child, $data);
        $this->em->persist($child);

        $this->audit->log(
            AuditAction::ChildCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Child',
            entityId: (string) $child->getId(),
        ); // flush

        return ChildResource::fromChild($child, $this->age->age($child->getBirthDate(), $ctx->family->getTimezone()));
    }
}
