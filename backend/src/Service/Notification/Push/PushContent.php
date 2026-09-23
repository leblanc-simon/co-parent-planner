<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

/**
 * Contenu d'un push, déjà rendu dans la langue du destinataire (Lot 11 §3.3).
 *
 * `data` ne porte que des **identifiants** (événement, famille, ressource) : c'est l'app qui relit
 * l'état canonique après ouverture. Aucun champ 🔒 n'y figure — le payload transite en clair par
 * les serveurs de Google et d'Apple, et s'affiche sur l'écran verrouillé.
 */
final readonly class PushContent
{
    /**
     * @param array<string, string> $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $data,
        public string $collapseKey,
    ) {
    }
}
