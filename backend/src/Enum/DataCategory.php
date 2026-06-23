<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie d'une donnée personnelle pour le registre des traitements (cdc §7.1).
 */
enum DataCategory: string
{
    case Identity = 'identity';      // identité (nom, prénom…)
    case Contact = 'contact';        // contact (e-mail, téléphone…)
    case Content = 'content';        // contenu produit (messages, notes…)
    case Technical = 'technical';    // technique (IP, horodatages, jetons…)
    case Authentication = 'authentication'; // secrets d'authentification (hash, jetons)
}
