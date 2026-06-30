<?php

declare(strict_types=1);

namespace App\Exception;

/** Incohérence entre le {id} de famille du chemin et le header X-Family-Id (§3.4). */
final class FamilyScopeMismatchException extends AbstractProblemException
{
    public const string TYPE = '/errors/family-scope-mismatch';

    public function __construct()
    {
        parent::__construct(400, self::TYPE, 'family.error.scope_mismatch');
    }
}
