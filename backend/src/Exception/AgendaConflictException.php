<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Conflit d'état sur un événement (Lot 4 §4.3) → 409 : verrou optimiste (version périmée) ou
 * occurrence déjà exclue. Reçoit une clé de traduction ICU.
 */
final class AgendaConflictException extends AbstractProblemException
{
    public const string TYPE = '/errors/conflict';

    public function __construct(string $detailKey = 'agenda.error.conflict')
    {
        parent::__construct(409, self::TYPE, $detailKey);
    }
}
