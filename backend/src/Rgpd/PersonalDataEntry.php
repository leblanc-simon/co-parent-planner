<?php

declare(strict_types=1);

namespace App\Rgpd;

use App\Attribute\PersonalData;

/**
 * Une entrée du registre des données personnelles : un champ d'entité marqué {@see PersonalData}.
 */
final readonly class PersonalDataEntry
{
    public function __construct(
        public string $entityShortName,
        public string $field,
        public PersonalData $meta,
    ) {
    }

    /** Clé canonique « Entité.champ » (référencée dans registre-donnees.md). */
    public function key(): string
    {
        return $this->entityShortName.'.'.$this->field;
    }
}
