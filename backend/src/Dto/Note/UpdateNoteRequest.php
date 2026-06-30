<?php

declare(strict_types=1);

namespace App\Dto\Note;

use Symfony\Component\Validator\Constraints as Assert;

/** Édition d'une note (US-CH-6) — PATCH partiel ; auteur ou parent/admin uniquement (§3.7). */
final class UpdateNoteRequest
{
    #[Assert\NotBlank(message: 'note.error.text_required', allowNull: true)]
    #[Assert\Length(max: 5000)]
    public ?string $text = null;

    public ?string $noteDate = null;
}
