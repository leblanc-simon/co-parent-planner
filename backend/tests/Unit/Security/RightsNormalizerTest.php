<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Enum\MembershipRole;
use App\Enum\RightLevel;
use App\Security\Membership\RightsNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation des droits selon le rôle (§3.1, §3.7).
 */
final class RightsNormalizerTest extends TestCase
{
    private RightsNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new RightsNormalizer();
    }

    public function testParentMaterializesNoRight(): void
    {
        $result = $this->normalizer->normalize(MembershipRole::Parent, ['agenda' => 'view', 'custody' => 'edit']);

        self::assertSame([], $result, 'Un parent est résolu par rôle, aucun Right matérialisé.');
    }

    public function testStepParentKeepsGranularRightsButNeverFamilySettings(): void
    {
        $result = $this->normalizer->normalize(MembershipRole::StepParent, [
            'agenda' => 'edit',
            'children_notes' => 'view',
            'family_settings' => 'edit',
        ]);

        self::assertSame(RightLevel::Edit, $result['agenda']);
        self::assertSame(RightLevel::View, $result['children_notes']);
        self::assertArrayNotHasKey('family_settings', $result, 'family_settings toujours none pour un beau-parent.');
    }

    public function testChildIsCappedAndForbiddenDomainsDropped(): void
    {
        $result = $this->normalizer->normalize(MembershipRole::Child, [
            'expenses' => 'view',
            'children_notes' => 'edit',
            'agenda' => 'edit',
            'custody' => 'view',
            'family_settings' => 'view',
        ]);

        self::assertArrayNotHasKey('expenses', $result, 'expenses interdit à un enfant.');
        self::assertArrayNotHasKey('family_settings', $result);
        self::assertSame(RightLevel::View, $result['children_notes'], 'children_notes plafonné à view.');
        self::assertSame(RightLevel::View, $result['agenda'], 'agenda plafonné à view.');
        self::assertSame(RightLevel::View, $result['custody']);
    }

    public function testNoneLevelsAreOmitted(): void
    {
        $result = $this->normalizer->normalize(MembershipRole::StepParent, ['agenda' => 'none', 'custody' => 'view']);

        self::assertArrayNotHasKey('agenda', $result);
        self::assertSame(RightLevel::View, $result['custody']);
    }
}
