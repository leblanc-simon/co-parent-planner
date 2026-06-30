<?php

declare(strict_types=1);

namespace App\Service\Child;

use App\Entity\Child;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\MembershipRepository;

/**
 * Construit le bloc « Accès & droits » d'un enfant (US-CH-2, §3.5) : qui peut voir/éditer, avec
 * quel niveau effectif. Les membres sans accès (`none`) ne sont pas listés. Tri : édition avant
 * lecture, puis rôle (parent, step_parent, child), puis nom.
 */
final class AccessRightsBuilder
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    /**
     * @return list<AccessEntry>
     */
    public function build(Child $child): array
    {
        $entries = [];
        foreach ($this->memberships->findActiveByFamily($child->getFamily()) as $m) {
            $level = $m->effectiveLevel(RightDomain::ChildrenNotes);
            if (RightLevel::None === $level) {
                continue;
            }
            $entries[] = new AccessEntry(
                membershipId: (string) $m->getId(),
                name: $m->getUser()->getFullName() ?? $m->getUser()->getEmail(),
                role: $m->getRole()->value,
                level: $level->value,
                isOwnProfile: null !== $m->getChildId() && $m->getChildId()->equals($child->getId()),
                displayColor: $m->getDisplayColor(),
            );
        }

        usort($entries, static function (AccessEntry $a, AccessEntry $b): int {
            // Édition avant lecture.
            $levelRank = self::levelRank($b->level) <=> self::levelRank($a->level);
            if (0 !== $levelRank) {
                return $levelRank;
            }
            $roleRank = self::roleRank($a->role) <=> self::roleRank($b->role);

            return 0 !== $roleRank ? $roleRank : strcasecmp($a->name, $b->name);
        });

        return $entries;
    }

    private static function levelRank(string $level): int
    {
        return RightLevel::Edit->value === $level ? 1 : 0;
    }

    private static function roleRank(string $role): int
    {
        return match ($role) {
            MembershipRole::Parent->value => 0,
            MembershipRole::StepParent->value => 1,
            default => 2,
        };
    }
}
