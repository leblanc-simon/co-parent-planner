<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Membership;
use App\Entity\Right;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Security\Voter\AbstractDomainVoter;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la table de vérité RBAC (cdc §2.2 / §3.7) au niveau Membership::effectiveLevel,
 * sur laquelle reposent tous les Voters par domaine.
 */
final class RbacTruthTableTest extends TestCase
{
    private function membership(MembershipRole $role, bool $admin = false, Right ...$rights): Membership
    {
        $m = (new Membership())->setRole($role)->setIsAdmin($admin);
        foreach ($rights as $r) {
            $m->addRight($r);
        }

        return $m;
    }

    public function testParentForcesEditEverywhereExceptStructuralWithoutAdmin(): void
    {
        $parent = $this->membership(MembershipRole::Parent, admin: false);

        foreach ([RightDomain::Custody, RightDomain::Agenda, RightDomain::ChildrenNotes, RightDomain::Expenses, RightDomain::Exchanges] as $d) {
            self::assertSame(RightLevel::Edit, $parent->effectiveLevel($d), "parent doit avoir edit sur {$d->value}");
        }

        // family_settings : parent NON admin → view seulement (pas d'édition de structure).
        self::assertSame(RightLevel::View, $parent->effectiveLevel(RightDomain::FamilySettings));
    }

    public function testAdminParentCanEditFamilySettings(): void
    {
        $admin = $this->membership(MembershipRole::Parent, admin: true);
        self::assertSame(RightLevel::Edit, $admin->effectiveLevel(RightDomain::FamilySettings));
    }

    public function testChildNeverHasExpensesNorStructure(): void
    {
        $child = $this->membership(
            MembershipRole::Child,
            false,
            // même si un droit expenses=edit est (à tort) déclaré, il est forcé none
            new Right(RightDomain::Expenses, RightLevel::Edit),
            new Right(RightDomain::Custody, RightLevel::View),
        );

        self::assertSame(RightLevel::None, $child->effectiveLevel(RightDomain::Expenses), 'enfant : expenses toujours none');
        self::assertSame(RightLevel::None, $child->effectiveLevel(RightDomain::FamilySettings), 'enfant : pas de structure');
        // Un domaine autorisé en lecture reste lecture.
        self::assertSame(RightLevel::View, $child->effectiveLevel(RightDomain::Custody));
        self::assertFalse($child->can(RightDomain::Custody, RightLevel::Edit), 'enfant : jamais d\'édition de structure custody');
    }

    public function testStepParentFollowsDeclaredRights(): void
    {
        $step = $this->membership(
            MembershipRole::StepParent,
            false,
            new Right(RightDomain::Agenda, RightLevel::Edit),
            new Right(RightDomain::ChildrenNotes, RightLevel::View),
        );

        self::assertSame(RightLevel::Edit, $step->effectiveLevel(RightDomain::Agenda));
        self::assertSame(RightLevel::View, $step->effectiveLevel(RightDomain::ChildrenNotes));
        // Domaine non déclaré → none par défaut.
        self::assertSame(RightLevel::None, $step->effectiveLevel(RightDomain::Exchanges));
        // family_settings : step_parent jamais (none).
        self::assertSame(RightLevel::None, $step->effectiveLevel(RightDomain::FamilySettings));
    }

    public function testInactiveMembershipHasNoRightsEvenAsParent(): void
    {
        // Précondition §3.1 : un membership non actif ne résout aucun droit, même rôle parent/admin.
        $invited = $this->membership(MembershipRole::Parent, admin: true)->setStatus(MembershipStatus::Invited);

        foreach (RightDomain::cases() as $domain) {
            self::assertSame(RightLevel::None, $invited->effectiveLevel($domain), "non actif → none sur {$domain->value}");
        }
    }

    public function testAgendaVoterMatrix(): void
    {
        // Parent → edit forcé (US-AG-1, §4.4).
        self::assertSame(RightLevel::Edit, $this->membership(MembershipRole::Parent)->effectiveLevel(RightDomain::Agenda));

        // Beau-parent → suit son droit déclaré (none par défaut, view ou edit).
        self::assertSame(RightLevel::None, $this->membership(MembershipRole::StepParent)->effectiveLevel(RightDomain::Agenda));
        $stepView = $this->membership(MembershipRole::StepParent, false, new Right(RightDomain::Agenda, RightLevel::View));
        self::assertTrue($stepView->can(RightDomain::Agenda, RightLevel::View));
        self::assertFalse($stepView->can(RightDomain::Agenda, RightLevel::Edit), 'beau-parent view ≠ edit');

        // Enfant : agenda ni interdit ni structurel → au mieux view (plafonné en amont par RightsNormalizer).
        $childView = $this->membership(MembershipRole::Child, false, new Right(RightDomain::Agenda, RightLevel::View));
        self::assertTrue($childView->can(RightDomain::Agenda, RightLevel::View));
        self::assertFalse($childView->can(RightDomain::Agenda, RightLevel::Edit), 'enfant : jamais edit');

        // Attributs du Voter agenda.
        self::assertSame('AGENDA_VIEW', AbstractDomainVoter::attribute(RightDomain::Agenda, RightLevel::View));
        self::assertSame('AGENDA_EDIT', AbstractDomainVoter::attribute(RightDomain::Agenda, RightLevel::Edit));
    }

    public function testLevelOrderingSatisfies(): void
    {
        self::assertTrue(RightLevel::Edit->satisfies(RightLevel::View));
        self::assertTrue(RightLevel::View->satisfies(RightLevel::View));
        self::assertFalse(RightLevel::View->satisfies(RightLevel::Edit));
        self::assertFalse(RightLevel::None->satisfies(RightLevel::View));
    }
}
