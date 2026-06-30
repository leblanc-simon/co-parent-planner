<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\AdminRequiresParentRoleException;
use App\Security\Membership\MembershipWriter;
use App\Security\Membership\RightsNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Écriture d'un membership (§3.7) : invariant is_admin ⇒ parent, application des droits normalisés.
 */
final class MembershipWriterTest extends TestCase
{
    private MembershipWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new MembershipWriter(new RightsNormalizer());
    }

    private function membership(): Membership
    {
        return (new Membership())->setUser(new User())->setFamily(new Family());
    }

    public function testAdminOnNonParentIsRejected(): void
    {
        $this->expectException(AdminRequiresParentRoleException::class);
        $this->writer->apply($this->membership(), MembershipRole::StepParent, true, []);
    }

    public function testParentAdminIsAccepted(): void
    {
        $membership = $this->membership();
        $this->writer->apply($membership, MembershipRole::Parent, true, ['agenda' => 'view']);

        self::assertSame(MembershipRole::Parent, $membership->getRole());
        self::assertTrue($membership->isAdmin());
        self::assertCount(0, $membership->getRights(), 'Parent : aucun Right matérialisé.');
    }

    public function testStepParentRightsAreMaterializedAndSynced(): void
    {
        $membership = $this->membership();
        $this->writer->apply($membership, MembershipRole::StepParent, false, ['agenda' => 'edit', 'custody' => 'view']);
        self::assertSame(RightLevel::Edit, $membership->effectiveLevel(RightDomain::Agenda));
        self::assertSame(RightLevel::View, $membership->effectiveLevel(RightDomain::Custody));

        // Réécriture : custody retombe à none (retiré), agenda passe view.
        $this->writer->apply($membership, MembershipRole::StepParent, false, ['agenda' => 'view']);
        self::assertSame(RightLevel::View, $membership->effectiveLevel(RightDomain::Agenda));
        self::assertSame(RightLevel::None, $membership->effectiveLevel(RightDomain::Custody));
    }
}
