<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use Symfony\Component\Validator\Constraints as Assert;

/** Upsert d'une préférence overlay du membre courant (US-LINK-1, §6.2). */
final class UpdateOverlayPreferenceRequest
{
    #[Assert\NotBlank]
    public ?string $familyLinkId = null;

    #[Assert\NotBlank]
    public ?string $view = null;

    public bool $enabled = false;
}
