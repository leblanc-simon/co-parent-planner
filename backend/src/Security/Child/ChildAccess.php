<?php

declare(strict_types=1);

namespace App\Security\Child;

use App\Entity\Child;
use App\Entity\Note;
use App\Enum\ChildNotesAction;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\ChildNotFoundException;
use App\Exception\DomainAccessDeniedException;
use App\Exception\NoteNotFoundException;
use App\Repository\ChildRepository;
use App\Repository\NoteRepository;
use App\Security\FamilyContext;
use App\Security\FamilyScope;
use Symfony\Component\Uid\Uuid;

/**
 * Résout et autorise les ressources `Child`/`Note` dans le scope de la famille active (Lot 2).
 *
 * Résolution **stricte** : une ressource hors scope famille renvoie 404 (non révélateur, §4.4).
 * L'autorisation passe par la {@see ChildNotesPolicy} (4 actions §3.3 + mutation de note §3.7).
 */
final class ChildAccess
{
    public function __construct(
        private readonly FamilyScope $scope,
        private readonly ChildRepository $children,
        private readonly NoteRepository $notes,
        private readonly ChildNotesPolicy $policy,
    ) {
    }

    public function context(): FamilyContext
    {
        return $this->scope->require();
    }

    /** Enfant résolu dans la famille active (sinon 404). */
    public function child(FamilyContext $ctx, mixed $id): Child
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new ChildNotFoundException();
        }
        $child = $this->children->find(Uuid::fromString($id));
        if (!$child instanceof Child || !$child->getFamily()->getId()->equals($ctx->family->getId())) {
            throw new ChildNotFoundException();
        }

        return $child;
    }

    /** Note résolue dans la famille active (via son enfant), sinon 404. */
    public function note(FamilyContext $ctx, mixed $id): Note
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new NoteNotFoundException();
        }
        $note = $this->notes->find(Uuid::fromString($id));
        if (!$note instanceof Note || !$note->getChild()->getFamily()->getId()->equals($ctx->family->getId())) {
            throw new NoteNotFoundException();
        }

        return $note;
    }

    /**
     * Exige une action du domaine children_notes (sinon 403 avec le droit requis).
     *
     * @throws DomainAccessDeniedException
     */
    public function authorize(FamilyContext $ctx, ChildNotesAction $action): void
    {
        if (!$this->policy->can($ctx->membership, $action)) {
            throw new DomainAccessDeniedException(RightDomain::ChildrenNotes, $this->requiredLevel($action));
        }
    }

    private function requiredLevel(ChildNotesAction $action): RightLevel
    {
        return match ($action) {
            ChildNotesAction::ChildView, ChildNotesAction::NoteView => RightLevel::View,
            ChildNotesAction::ChildManage, ChildNotesAction::NoteEdit => RightLevel::Edit,
        };
    }
}
