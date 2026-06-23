<?php

declare(strict_types=1);

namespace App\Attribute;

use App\Enum\DataCategory;

/**
 * Marque déclarative d'un champ personnel pour le **registre des données personnelles** (cdc §7.1).
 *
 * Source de vérité vivante : chaque propriété d'entité portant cet attribut doit avoir une
 * entrée correspondante dans `/backend/docs/registre-donnees.md`. Un test de cohérence CI
 * (App\Tests\Rgpd\PersonalDataRegistryTest) échoue sinon, et échoue aussi si un champ chiffré 🔒
 * n'est pas marqué personnel.
 *
 * Obligation : toute évolution du modèle introduisant un champ personnel ajoute cet attribut
 * + une entrée de registre (DoD du lot concerné).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PersonalData
{
    /**
     * @param bool $encrypted Champ chiffré au repos (🔒, AES-256-GCM)
     */
    public function __construct(
        public DataCategory $category,
        public string $purpose,
        public string $legalBasis,
        public string $retention,
        public string $recipients = 'Membres habilités de la famille',
        public bool $encrypted = false,
    ) {
    }
}
