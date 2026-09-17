<?php

declare(strict_types=1);

namespace App\Exception;

/** Donnée de dépense invalide (Lot 10 §4.2/§4.6) → 422. Reçoit une clé de traduction ICU. */
final class ExpenseInputException extends AbstractProblemException
{
    public const string TYPE = '/errors/validation';

    /** @param array<string, string|int> $detailParams */
    public function __construct(string $detailKey, array $detailParams = [])
    {
        parent::__construct(422, self::TYPE, $detailKey, $detailParams);
    }
}
