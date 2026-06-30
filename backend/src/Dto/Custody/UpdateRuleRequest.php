<?php

declare(strict_types=1);

namespace App\Dto\Custody;

/** Définition/mise à jour d'une règle de période (US-CUST-3). `params` validé selon `kind`. */
final class UpdateRuleRequest
{
    public ?string $kind = null;
    /** @var array<string, mixed>|null */
    public ?array $params = null;
}
