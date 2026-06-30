<?php

declare(strict_types=1);

namespace App\Exception;

/** Entrée de garde invalide (422) — message localisé porté par la clé de traduction. */
final class CustodyInputException extends AbstractProblemException
{
    public const string TYPE = '/errors/validation';

    public function __construct(string $detailKey)
    {
        parent::__construct(422, self::TYPE, $detailKey);
    }
}
