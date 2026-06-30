<?php

declare(strict_types=1);

namespace App\State\Note;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Exception\NoteForbiddenException;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use App\Security\Child\ChildNotesPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-6 — supprime une note (droit `NOTE_EDIT` + auteur ou parent/admin §3.7).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeleteNoteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChildAccess $access,
        private readonly ChildNotesPolicy $policy,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->context();
        $note = $this->access->note($ctx, $uriVariables['id'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::NoteEdit);
        if (!$this->policy->canMutateNote($ctx->membership, $note)) {
            throw new NoteForbiddenException();
        }

        $noteId = (string) $note->getId();
        $this->em->remove($note);

        $this->audit->log(
            AuditAction::NoteDeleted,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Note',
            entityId: $noteId,
        ); // flush

        return null;
    }
}
