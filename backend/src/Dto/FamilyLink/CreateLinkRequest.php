<?php

declare(strict_types=1);

namespace App\Dto\FamilyLink;

use Symfony\Component\Validator\Constraints as Assert;

/** Proposition d'un lien entre familles (US-LINK-1). La cible est désignée par son identifiant. */
final class CreateLinkRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $linkedFamilyId = null;
}
