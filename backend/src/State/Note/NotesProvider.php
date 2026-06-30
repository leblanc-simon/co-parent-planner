<?php

declare(strict_types=1);

namespace App\State\Note;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Note\NoteResource;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Repository\NoteRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-6 — liste les notes d'un enfant (droit `children_notes ≥ view`), text déchiffré, tri date desc.
 * L'accès aux notes est une action sensible (§5.4) → un audit agrégé par consultation.
 *
 * @implements ProviderInterface<NoteResource>
 */
final class NotesProvider implements ProviderInterface
{
    public function __construct(
        private readonly ChildAccess $access,
        private readonly NoteRepository $notes,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<NoteResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['childId'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::NoteView);

        $notes = $this->notes->findByChild($child);

        $this->audit->log(
            AuditAction::NoteAccessed,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['child' => (string) $child->getId(), 'count' => \count($notes)],
            entityType: 'Child',
            entityId: (string) $child->getId(),
        );

        return array_map(static fn ($note) => NoteResource::fromNote($note), $notes);
    }
}
