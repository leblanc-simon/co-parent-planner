<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Conflit d'état sur un échange (Lot 6 §4.2) → 409 : décision déjà tranchée, réouverture interdite,
 * ou verrou optimiste périmé. Reçoit une clé de traduction ICU. Le client (outbox mobile) se réaligne.
 */
final class ExchangeConflictException extends AbstractProblemException
{
    public const string TYPE = '/errors/conflict';

    public function __construct(string $detailKey = 'exchange.error.conflict')
    {
        parent::__construct(409, self::TYPE, $detailKey);
    }
}
