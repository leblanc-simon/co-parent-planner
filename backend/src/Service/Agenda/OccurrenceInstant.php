<?php

declare(strict_types=1);

namespace App\Service\Agenda;

/**
 * Instant concret produit par l'expansion d'une série (Lot 4 §3.2), avant application des
 * overrides/EXDATE et rattachement aux champs du maître. `startsAt`/`endsAt` sont des instants
 * **UTC** ; `recurrenceDate` est la **date locale d'origine** (minuit fuseau famille) qui sert de
 * clé d'occurrence (§3.3).
 */
final class OccurrenceInstant
{
    public function __construct(
        public readonly \DateTimeImmutable $startsAt,
        public readonly ?\DateTimeImmutable $endsAt,
        public readonly \DateTimeImmutable $recurrenceDate,
    ) {
    }

    /** Clé d'occurrence (date d'origine, Y-m-d local). */
    public function dateKey(): string
    {
        return $this->recurrenceDate->format('Y-m-d');
    }
}
