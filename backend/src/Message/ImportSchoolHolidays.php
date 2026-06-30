<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Demande d'import asynchrone des vacances officielles d'une famille (Lot 3 §3.10), traitée par un
 * worker avec retries/backoff. Idempotent (upsert sur clé naturelle).
 */
final readonly class ImportSchoolHolidays
{
    public function __construct(
        public string $familyId,
        public ?string $schoolYear = null,
    ) {
    }
}
