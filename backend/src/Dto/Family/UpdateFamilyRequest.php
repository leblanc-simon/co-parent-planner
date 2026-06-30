<?php

declare(strict_types=1);

namespace App\Dto\Family;

use App\Enum\FamilyType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglages éditables d'une famille (US-SET-2a). PATCH partiel : seules les clés **présentes**
 * dans le corps JSON sont appliquées (cf. {@see \App\State\Family\UpdateFamilyProcessor}).
 * Les champs garde (jour/heure d'échange, zone, académie) sont **persistés sans interprétation**
 * au Lot 1 (exploités au Lot 3).
 */
final class UpdateFamilyRequest
{
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 1, max: 120)]
    public ?string $name = null;

    #[Assert\Choice(callback: [self::class, 'validTypes'])]
    public ?string $type = null;

    #[Assert\Timezone]
    public ?string $timezone = null;

    #[Assert\Choice(choices: ['A', 'B', 'C'])]
    public ?string $schoolZone = null;

    #[Assert\Length(max: 120)]
    public ?string $academy = null;

    public ?bool $expensesEnabled = null;

    /** Jour d'échange de garde : 1 (lundi) … 7 (dimanche) — ISO-8601. */
    #[Assert\Range(min: 1, max: 7)]
    public ?int $handoverWeekday = null;

    /** Heure d'échange locale famille, format HH:MM (00:00–23:59). */
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: "L'heure d'échange doit être au format HH:MM.")]
    public ?string $handoverTime = null;

    /** @return list<string> */
    public static function validTypes(): array
    {
        return array_map(static fn (FamilyType $t): string => $t->value, FamilyType::cases());
    }
}
