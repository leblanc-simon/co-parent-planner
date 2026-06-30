<?php

declare(strict_types=1);

namespace App\Dto\Member;

use App\Enum\MembershipRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mutation d'un membre (US-MBR-2/5). PATCH partiel : seules les clés présentes sont appliquées.
 * `role`/`isAdmin`/`rights` exigent `family_settings:edit` (admin) ; `displayColor` est éditable
 * par le membre lui-même.
 */
final class UpdateMembershipRequest
{
    #[Assert\Choice(callback: [self::class, 'validRoles'])]
    public ?string $role = null;

    public ?bool $isAdmin = null;

    /** @var array<string, string>|null domaine → niveau proposé */
    public ?array $rights = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être un code hexadécimal #RRGGBB.')]
    public ?string $displayColor = null;

    /** @return list<string> */
    public static function validRoles(): array
    {
        return array_map(static fn (MembershipRole $r): string => $r->value, MembershipRole::cases());
    }
}
