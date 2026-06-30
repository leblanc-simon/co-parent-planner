<?php

declare(strict_types=1);

namespace App\Dto\Custody;

/**
 * Création/modification du rythme de garde (US-CUST-1/2/9). La validation conditionnelle (selon
 * `kind`) et la résolution des parents A/B sont faites par {@see \App\Service\Custody\PatternWriter}.
 */
final class UpdatePatternRequest
{
    public ?string $kind = null;
    public ?string $anchorDate = null;
    public ?int $handoverWeekday = null;
    public ?string $handoverTime = null;
    /** @var list<string>|null */
    public ?array $customPattern = null;
    /** @var array<string, mixed>|null */
    public ?array $settings = null;
    public ?string $parentAMembershipId = null;
    public ?string $parentBMembershipId = null;
}
