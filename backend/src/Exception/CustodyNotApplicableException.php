<?php

declare(strict_types=1);

namespace App\Exception;

/** Mutation de garde sur une famille unie (`intact`) — conflit d'état (409, §US-CUST-1). */
final class CustodyNotApplicableException extends AbstractProblemException
{
    public const string TYPE = '/errors/custody-not-applicable';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'custody.error.not_applicable');
    }
}
