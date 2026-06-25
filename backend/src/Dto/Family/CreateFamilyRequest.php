<?php

declare(strict_types=1);

namespace App\Dto\Family;

use App\Enum\FamilyType;
use Symfony\Component\Validator\Constraints as Assert;

/** Création de ma première famille (US-ONB-1) : nom + type. */
final class CreateFamilyRequest
{
    // Champs requis sans valeur par defaut : un `= ''` exposerait un `default: ""`
    // dans l'OpenAPI (invalide pour l'enum `type`, contradictoire avec minLength
    // pour `name`) et casse la generation du client mobile. Nullable + NotBlank
    // = pattern d'entree API Platform : presence imposee a la validation (422).
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'validTypes'])]
    public ?string $type = null;

    /** @return list<string> */
    public static function validTypes(): array
    {
        return array_map(static fn (FamilyType $t): string => $t->value, FamilyType::cases());
    }
}
