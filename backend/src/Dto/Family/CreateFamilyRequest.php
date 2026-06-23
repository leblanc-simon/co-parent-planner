<?php

declare(strict_types=1);

namespace App\Dto\Family;

use App\Enum\FamilyType;
use Symfony\Component\Validator\Constraints as Assert;

/** Création de ma première famille (US-ONB-1) : nom + type. */
final class CreateFamilyRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 120)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'validTypes'])]
    public string $type = '';

    /** @return list<string> */
    public static function validTypes(): array
    {
        return array_map(static fn (FamilyType $t): string => $t->value, FamilyType::cases());
    }
}
