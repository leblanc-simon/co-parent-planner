<?php

declare(strict_types=1);

namespace App\Rgpd;

/**
 * Résultat (ou plan, en dry-run) d'une anonymisation de compte (Lot 8 §3.3).
 */
final readonly class AnonymizationResult
{
    /**
     * @param list<string> $fieldsNeutralized champs personnels neutralisés (ou qui le seraient)
     */
    public function __construct(
        public bool $alreadyAnonymized,
        public array $fieldsNeutralized,
        public int $membershipsRemoved,
        public int $notificationPrefsRemoved,
    ) {
    }
}
