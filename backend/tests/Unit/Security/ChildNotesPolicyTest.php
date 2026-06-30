<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Child;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\Note;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\ChildNotesAction;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Security\Child\ChildNotesPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Politique children_notes (§3.3, §3.7) : matrice rôle × niveau × action + mutation de note.
 */
final class ChildNotesPolicyTest extends TestCase
{
    private ChildNotesPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ChildNotesPolicy();
    }

    private function membership(MembershipRole $role, ?RightLevel $level = null): Membership
    {
        $m = (new Membership())->setUser(new User())->setFamily(new Family())->setRole($role);
        if (null !== $level) {
            $m->addRight(new Right(RightDomain::ChildrenNotes, $level));
        }

        return $m;
    }

    public function testParentGrantedEverything(): void
    {
        $m = $this->membership(MembershipRole::Parent);
        foreach (ChildNotesAction::cases() as $action) {
            self::assertTrue($this->policy->can($m, $action), $action->name);
        }
    }

    public function testStepParentEditManagesProfileAndNotes(): void
    {
        $m = $this->membership(MembershipRole::StepParent, RightLevel::Edit);
        self::assertTrue($this->policy->can($m, ChildNotesAction::ChildManage));
        self::assertTrue($this->policy->can($m, ChildNotesAction::NoteEdit));
        self::assertTrue($this->policy->can($m, ChildNotesAction::ChildView));
    }

    public function testStepParentViewIsReadOnly(): void
    {
        $m = $this->membership(MembershipRole::StepParent, RightLevel::View);
        self::assertTrue($this->policy->can($m, ChildNotesAction::ChildView));
        self::assertTrue($this->policy->can($m, ChildNotesAction::NoteView));
        self::assertFalse($this->policy->can($m, ChildNotesAction::ChildManage));
        self::assertFalse($this->policy->can($m, ChildNotesAction::NoteEdit));
    }

    public function testChildDefaultNoneSeesNothing(): void
    {
        $m = $this->membership(MembershipRole::Child);
        self::assertFalse($this->policy->can($m, ChildNotesAction::ChildView));
        self::assertFalse($this->policy->can($m, ChildNotesAction::NoteView));
    }

    public function testChildEditCanNotesButNeverManagesStructure(): void
    {
        // Cas critique §3.3 : child + children_notes=edit → NOTE_EDIT oui, CHILD_MANAGE NON.
        $m = $this->membership(MembershipRole::Child, RightLevel::Edit);
        self::assertTrue($this->policy->can($m, ChildNotesAction::NoteEdit));
        self::assertTrue($this->policy->can($m, ChildNotesAction::NoteView));
        self::assertFalse($this->policy->can($m, ChildNotesAction::ChildManage), 'enfant ne gère jamais la structure');
    }

    public function testMutateNoteAuthorAndParentOnly(): void
    {
        $child = new Child();
        $author = $this->membership(MembershipRole::StepParent, RightLevel::Edit);
        $other = $this->membership(MembershipRole::StepParent, RightLevel::Edit);
        $parent = $this->membership(MembershipRole::Parent);

        $note = (new Note())->setChild($child)->setAuthorMembership($author)->setText('x')->setNoteDate(new \DateTimeImmutable());

        self::assertTrue($this->policy->canMutateNote($author, $note), 'auteur');
        self::assertTrue($this->policy->canMutateNote($parent, $note), 'parent modère');
        self::assertFalse($this->policy->canMutateNote($other, $note), 'beau-parent non-auteur refusé');
    }
}
