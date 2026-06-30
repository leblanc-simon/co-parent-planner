<?php

declare(strict_types=1);

namespace App\State\Note;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Note\CreateNoteRequest;
use App\Dto\Note\NoteResource;
use App\Entity\Note;
use App\Enum\AuditAction;
use App\Enum\ChildNotesAction;
use App\Security\Auth\AuditLogger;
use App\Security\Child\ChildAccess;
use App\Service\Note\NotePublisher;
use App\Service\Note\NoteWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CH-6 — ajoute une note (droit `NOTE_EDIT`). Texte chiffré au repos ; push Mercure après commit.
 *
 * @implements ProcessorInterface<CreateNoteRequest, NoteResource>
 */
final class CreateNoteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChildAccess $access,
        private readonly NoteWriter $writer,
        private readonly NotePublisher $publisher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): NoteResource
    {
        \assert($data instanceof CreateNoteRequest);

        $ctx = $this->access->context();
        $child = $this->access->child($ctx, $uriVariables['childId'] ?? null);
        $this->access->authorize($ctx, ChildNotesAction::NoteEdit);

        $note = (new Note())->setChild($child)->setAuthorMembership($ctx->membership);
        $this->writer->applyCreate($note, $data, $child, $ctx->family->getTimezone());
        $this->em->persist($note);

        $this->audit->log(
            AuditAction::NoteCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Note',
            entityId: (string) $note->getId(),
        ); // flush (commit)

        // Après commit : diffusion temps réel best-effort (n'invalide jamais l'écriture).
        $this->publisher->publishCreated($note);

        return NoteResource::fromNote($note);
    }
}
