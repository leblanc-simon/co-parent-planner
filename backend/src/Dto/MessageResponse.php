<?php

declare(strict_types=1);

namespace App\Dto;

/** Réponse générique localisée (succès non énumérant, accusés de réception). */
final readonly class MessageResponse
{
    public function __construct(
        public string $message,
    ) {
    }
}
