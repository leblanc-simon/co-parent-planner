<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

/**
 * Charge utile d'édition d'un échange (Lot 6 §6.1). `status=decided` clôt une discussion ;
 * `decidedOptionId` force une décision ; `deadline` : `Y-m-d` fixe, `''` efface, `null` inchangé.
 * `expectedVersion` arme le verrou optimiste.
 */
class UpdateExchangeRequest
{
    public ?string $status = null;
    public ?string $decidedOptionId = null;
    public ?string $deadline = null;
    public ?int $expectedVersion = null;
}
