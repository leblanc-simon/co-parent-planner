<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

/** Charge utile de validation d'une option (Lot 6 §6.1) : `optionId` d'une option de la décision. */
class DecideRequest
{
    public ?string $optionId = null;
}
