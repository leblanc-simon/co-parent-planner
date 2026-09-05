<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use App\Enum\CustodyPalette;
use Symfony\Component\Validator\Constraints as Assert;

/** Upsert d'une couleur de planning du membre courant (cdc §8.2). */
final class UpdateCustodyColorRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $targetMembershipId = null;

    /** Palette fermée : une valeur hors catalogue est refusée, jamais silencieusement ignorée. */
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [CustodyPalette::class, 'values'])]
    public ?string $palette = null;
}
