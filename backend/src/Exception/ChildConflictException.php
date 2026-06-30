<?php

declare(strict_types=1);

namespace App\Exception;

/** Conflit d'édition concurrente d'un profil enfant (verrou optimiste, §4.2). */
final class ChildConflictException extends AbstractProblemException
{
    public const string TYPE = '/errors/conflict';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'child.error.conflict');
    }
}
