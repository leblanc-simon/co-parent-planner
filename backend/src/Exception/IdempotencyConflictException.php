<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Clé d'idempotence réutilisée pour une requête différente (payload/scope divergent) ou mutation
 * encore en cours (§3.1/§4.2) : 409, jamais de réponse mémorisée servie à tort.
 */
final class IdempotencyConflictException extends AbstractProblemException
{
    public const string TYPE = '/errors/idempotency-conflict';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'idempotency.error.conflict');
    }
}
