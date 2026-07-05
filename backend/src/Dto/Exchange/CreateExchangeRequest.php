<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

/**
 * Charge utile de création d'un échange (Lot 6 §6.3). `type` = `discussion|decision` ; `childId` nul
 * = « toute la famille » ; `deadline` = date `Y-m-d` optionnelle ; `options` requis (≥ 2) si décision.
 */
class CreateExchangeRequest
{
    public ?string $type = null;
    public ?string $title = null;
    public ?string $body = null;
    public ?string $childId = null;
    public ?string $deadline = null;

    /**
     * @var array<int, array{label?: string|null, sub?: string|null, position?: int|null}>|null
     */
    public ?array $options = null;
}
