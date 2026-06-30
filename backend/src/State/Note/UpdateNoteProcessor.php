<?php

declare(strict_types=1);

namespace App\State\Note;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Note\NoteResource;
use App\Dto\Note\UpdateNoteRequest;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Exception\NoteForbiddenException;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use App\Security\Child\ChildNotesPolicy;
use App\Service\Note\NoteWriter;
use App\State\Concern\ReadsProvidedKeys;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-6 — édite une note (droit `NOTE_EDIT` + auteur ou parent/admin §3.7). Re-chiffrement du texte.
 *
 * @implements ProcessorInterface<UpdateNoteRequest, NoteResource>
 */
final class UpdateNoteProcessor implements ProcessorInterface
{
    use ReadsProvidedKeys;

    public function __construct(
        private readonly ChildAccess $access,
        private readonly ChildNotesPolicy $policy,
        private readonly NoteWriter $writer,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NoteResource
    {
        \assert($data instanceof UpdateNoteRequest);

        $ctx = $this->access->context();
        $note = $this->access->note($ctx, $uriVariables['id'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::NoteEdit);
        if (!$this->policy->canMutateNote($ctx->membership, $note)) {
            throw new NoteForbiddenException();
        }

        $this->writer->applyUpdate($note, $data, $this->providedKeys(), $ctx->family->getTimezone());

        $this->audit->log(
            AuditAction::NoteUpdated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Note',
            entityId: (string) $note->getId(),
        ); // flush

        return NoteResource::fromNote($note);
    }

    protected function requestStack(): RequestStack
    {
        return $this->requestStack;
    }
}
