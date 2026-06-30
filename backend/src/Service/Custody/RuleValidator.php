<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Enum\HolidayRuleKind;
use App\Exception\CustodyInputException;

/**
 * Valide les `params` d'une `HolidayRule` selon son `kind` (Lot 3 §5.1). 422 si incohérent.
 */
final class RuleValidator
{
    /**
     * @param array<string, mixed> $params
     */
    public function validate(HolidayRuleKind $kind, array $params): void
    {
        $ok = match ($kind) {
            HolidayRuleKind::FixedParent => $this->slot($params['parent'] ?? null),
            HolidayRuleKind::Split5050 => $this->slot($params['firstHalf'] ?? null) && $this->slot($params['secondHalf'] ?? null),
            HolidayRuleKind::AlternatingWeeks => $this->slot($params['startParent'] ?? null),
            HolidayRuleKind::EveAlternating => $this->slot($params['evenParent'] ?? null),
            HolidayRuleKind::Custom => $this->validCustom($params['assignments'] ?? null),
        };

        if (!$ok) {
            throw new CustodyInputException('custody.error.rule_params_invalid');
        }
    }

    private function slot(mixed $value): bool
    {
        return 'A' === $value || 'B' === $value;
    }

    private function validCustom(mixed $assignments): bool
    {
        if (!\is_array($assignments) || [] === $assignments) {
            return false;
        }
        foreach ($assignments as $date => $slot) {
            if (!\is_string($date) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$this->slot($slot)) {
                return false;
            }
        }

        return true;
    }
}
