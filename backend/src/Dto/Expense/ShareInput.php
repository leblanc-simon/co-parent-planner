<?php

declare(strict_types=1);

namespace App\Dto\Expense;

/**
 * Une part saisie (Lot 10 §3.1). `value` est un **entier** : pourcentage de 0 à 100, ou centimes
 * selon `shareType` — aucune fraction de centime, aucun flottant ne traverse le contrat (§4.2).
 */
final class ShareInput
{
    public ?string $membershipId = null;

    /** `percent` ou `amount` — homogène sur toute la dépense (§3.1, règle 2). */
    public ?string $shareType = null;

    public ?int $value = null;
}
