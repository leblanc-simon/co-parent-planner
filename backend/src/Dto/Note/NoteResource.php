<?php

declare(strict_types=1);

namespace App\Dto\Note;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Child\ChildResource;
use App\Entity\Note;
use App\State\Note\CreateNoteProcessor;
use App\State\Note\DeleteNoteProcessor;
use App\State\Note\NotesProvider;
use App\State\Note\UpdateNoteProcessor;

/**
 * Note partagée exposée (US-CH-6). `text` déchiffré au rendu serveur ; auteur résolu (nom + couleur).
 */
#[ApiResource(
    shortName: 'Note',
    operations: [
        new GetCollection(
            uriTemplate: '/children/{childId}/notes',
            uriVariables: ['childId' => new Link(fromClass: ChildResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: NotesProvider::class,
        ),
        new Post(
            uriTemplate: '/children/{childId}/notes',
            uriVariables: ['childId' => new Link(fromClass: ChildResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateNoteRequest::class,
            read: false,
            processor: CreateNoteProcessor::class,
        ),
        new Patch(
            uriTemplate: '/notes/{id}',
            input: UpdateNoteRequest::class,
            read: false,
            processor: UpdateNoteProcessor::class,
        ),
        new Delete(uriTemplate: '/notes/{id}', output: false, read: false, processor: DeleteNoteProcessor::class),
    ],
)]
class NoteResource
{
    public string $id = '';
    public string $childId = '';
    public string $text = '';
    public ?string $noteDate = null;
    /** Libellé français de `noteDate` : le gabarit affichait la valeur ISO à l'écran. */
    public ?string $noteDateLabel = null;
    public string $authorMembershipId = '';
    public string $authorName = '';
    public ?string $authorColor = null;
    public ?string $createdAt = null;

    public static function fromNote(Note $note): self
    {
        $author = $note->getAuthorMembership();

        $dto = new self();
        $dto->id = (string) $note->getId();
        $dto->childId = (string) $note->getChild()->getId();
        $dto->text = $note->getText();
        $dto->noteDate = $note->getNoteDate()->format('Y-m-d');
        $dto->noteDateLabel = $note->getNoteDate()->format('d/m/Y');
        // Auteur anonymisé (membre retiré) : fil préservé, identité neutralisée (§4.8).
        if (null !== $author) {
            $dto->authorMembershipId = (string) $author->getId();
            $dto->authorName = $author->getUser()->getFullName() ?: $author->getUser()->getEmail();
            $dto->authorColor = $author->getDisplayColor();
        } else {
            $dto->authorName = 'Membre supprimé';
        }
        $dto->createdAt = $note->getCreatedAt()->format(\DATE_ATOM);

        return $dto;
    }
}
