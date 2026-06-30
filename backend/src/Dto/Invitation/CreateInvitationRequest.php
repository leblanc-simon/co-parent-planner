<?php

declare(strict_types=1);

namespace App\Dto\Invitation;

use App\Enum\MembershipRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création d'une invitation (US-INV-1) : e-mail + rôle + droits proposés (récap UI avant envoi).
 */
final class CreateInvitationRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'validRoles'])]
    public ?string $role = null;

    /** @var array<string, string>|null domaine → niveau proposé */
    public ?array $rights = null;

    /** Admin proposé (honoré seulement pour role=parent). */
    public ?bool $isAdmin = null;

    /** @return list<string> */
    public static function validRoles(): array
    {
        return array_map(static fn (MembershipRole $r): string => $r->value, MembershipRole::cases());
    }
}
