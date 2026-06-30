<?php

declare(strict_types=1);

namespace App\Security\Child;

use App\Entity\Membership;
use App\Entity\Note;
use App\Enum\ChildNotesAction;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;

/**
 * Politique d'autorisation du domaine children_notes (Lot 2 §3.3 + §3.7).
 *
 * Quatre actions (CHILD_VIEW/CHILD_MANAGE/NOTE_VIEW/NOTE_EDIT) séparant structure et contenu.
 * Invariant clé : un `child` ne gère **jamais** un profil (CHILD_MANAGE refusé), même s'il a
 * `children_notes=edit` (auquel cas il peut tout de même éditer ses propres notes).
 */
final class ChildNotesPolicy
{
    /** Autorise une action sur le domaine children_notes (la ressource est déjà scopée famille). */
    public function can(Membership $membership, ChildNotesAction $action): bool
    {
        if (!$membership->isActive()) {
            return false;
        }

        $level = $membership->effectiveLevel(RightDomain::ChildrenNotes);

        return match ($action) {
            ChildNotesAction::ChildView,
            ChildNotesAction::NoteView => $level->satisfies(RightLevel::View),
            // Structure : édition requise ET jamais un enfant (garde-fou §3.3 étape 3).
            ChildNotesAction::ChildManage => RightLevel::Edit === $level && !$membership->getRole()->isChild(),
            ChildNotesAction::NoteEdit => RightLevel::Edit === $level,
        };
    }

    /**
     * Mutation (édition/suppression) d'une note **existante** (§3.7) : réservée à l'auteur et aux
     * parents/admins. Prérequis : `can(..., NoteEdit)` déjà vérifié par l'appelant.
     */
    public function canMutateNote(Membership $membership, Note $note): bool
    {
        if (MembershipRole::Parent === $membership->getRole()) {
            return true; // parent/admin : modération de toute note
        }

        $author = $note->getAuthorMembership();

        // Note d'un auteur retiré (anonymisé) : seul un parent/admin peut la modérer.
        return null !== $author && $author->getId()->equals($membership->getId());
    }
}
