<?php

declare(strict_types=1);

namespace App\Exception;

/** Donnée d'agenda invalide (Lot 4 §4.6) → 422. Reçoit une clé de traduction ICU. */
final class AgendaInputException extends AbstractProblemException
{
    public const string TYPE = '/errors/validation';

    public function __construct(string $detailKey)
    {
        parent::__construct(422, self::TYPE, $detailKey);
    }
}
