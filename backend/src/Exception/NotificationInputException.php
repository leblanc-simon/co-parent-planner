<?php

declare(strict_types=1);

namespace App\Exception;

/** Préférence de notification invalide (Lot 7 §2 US-SET-1) → 422. Reçoit une clé de traduction ICU. */
final class NotificationInputException extends AbstractProblemException
{
    public const string TYPE = '/errors/validation';

    public function __construct(string $detailKey)
    {
        parent::__construct(422, self::TYPE, $detailKey);
    }
}
