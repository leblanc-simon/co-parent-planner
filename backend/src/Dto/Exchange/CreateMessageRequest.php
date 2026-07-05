<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

/** Charge utile de pose d'un message (Lot 6 §6.1) : `text` non vide, ≤ 5000 caractères. */
class CreateMessageRequest
{
    public ?string $text = null;
}
