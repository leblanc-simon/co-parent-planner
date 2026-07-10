<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Ordre d'assemblage asynchrone d'un export RGPD (§3.5). Ne porte que des identifiants (jamais de
 * donnée personnelle/🔒 en clair dans la file) ; le handler recharge et déchiffre en mémoire.
 */
final readonly class ExportUserData
{
    public function __construct(
        public string $operationId,
        public string $subjectUserId,
    ) {
    }
}
