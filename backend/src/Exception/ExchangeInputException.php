<?php

declare(strict_types=1);

namespace App\Exception;

/** Donnée d'échange invalide (Lot 6 §4.5) → 422. Reçoit une clé de traduction ICU. */
final class ExchangeInputException extends AbstractProblemException
{
    public const string TYPE = '/errors/validation';

    public function __construct(string $detailKey)
    {
        parent::__construct(422, self::TYPE, $detailKey);
    }
}
