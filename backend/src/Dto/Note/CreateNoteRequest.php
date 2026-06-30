<?php

declare(strict_types=1);

namespace App\Dto\Note;

use Symfony\Component\Validator\Constraints as Assert;

/** Ajout d'une note (US-CH-6). `noteDate` optionnelle → défaut aujourd'hui (fuseau famille). */
final class CreateNoteRequest
{
    #[Assert\NotBlank(message: 'note.error.text_required')]
    #[Assert\Length(max: 5000)]
    public ?string $text = null;

    /** Date ISO (YYYY-MM-DD) ; null → aujourd'hui. */
    public ?string $noteDate = null;
}
