<?php

declare(strict_types=1);

namespace App\Dto\Child;

use Symfony\Component\Validator\Constraints as Assert;

/** Édition d'un profil enfant (US-CH-1, PATCH partiel : seules les clés présentes sont appliquées). */
final class UpdateChildRequest
{
    #[Assert\NotBlank(message: 'child.error.first_name_required', allowNull: true)]
    #[Assert\Length(max: 120)]
    public ?string $firstName = null;

    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être un code hexadécimal #RRGGBB.')]
    public ?string $color = null;

    public ?string $birthDate = null;

    #[Assert\Length(max: 120)]
    public ?string $schoolLabel = null;

    /** @var array<int, array<string, mixed>>|null */
    public ?array $info = null;

    /**
     * Version attendue (verrou optimiste, §4.2) : si fournie et différente de la version courante,
     * l'édition est rejetée par un 409 (évite l'écrasement concurrent de `info`). Optionnel.
     */
    public ?int $expectedVersion = null;
}
