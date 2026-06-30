<?php

declare(strict_types=1);

namespace App\State\Child;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Child\ChildResource;
use App\Dto\Child\UpdateChildRequest;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use App\Service\Child\AgeCalculator;
use App\Service\Child\ChildWriter;
use App\State\Concern\ReadsProvidedKeys;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-1 — édite un profil enfant (droit `CHILD_MANAGE`). PATCH partiel. Verrou optimiste sur `info`.
 *
 * @implements ProcessorInterface<UpdateChildRequest, ChildResource>
 */
final class UpdateChildProcessor implements ProcessorInterface
{
    use ReadsProvidedKeys;

    public function __construct(
        private readonly ChildAccess $access,
        private readonly ChildWriter $writer,
        private readonly AgeCalculator $age,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChildResource
    {
        \assert($data instanceof UpdateChildRequest);

        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['id'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::ChildManage);

        $this->writer->applyUpdate($child, $data, $this->providedKeys());

        $this->audit->log(
            AuditAction::ChildUpdated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Child',
            entityId: (string) $child->getId(),
        ); // flush

        return ChildResource::fromChild($child, $this->age->age($child->getBirthDate(), $ctx->family->getTimezone()));
    }

    protected function requestStack(): RequestStack
    {
        return $this->requestStack;
    }
}
