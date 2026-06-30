<?php

declare(strict_types=1);

namespace App\Dto\Child;

use Symfony\Component\Validator\Constraints as Assert;

/** Création d'un profil enfant (US-CH-4). `info` : paires libres, aucun champ médical typé. */
final class CreateChildRequest
{
    #[Assert\NotBlank(message: 'child.error.first_name_required')]
    #[Assert\Length(max: 120)]
    public ?string $firstName = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être un code hexadécimal #RRGGBB.')]
    public ?string $color = null;

    /** Date ISO (YYYY-MM-DD) ou null. */
    public ?string $birthDate = null;

    #[Assert\Length(max: 120)]
    public ?string $schoolLabel = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $info = null;
}
