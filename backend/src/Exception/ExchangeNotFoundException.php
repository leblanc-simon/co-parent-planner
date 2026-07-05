<?php

declare(strict_types=1);

namespace App\Exception;

/** Échange introuvable ou hors scope famille (Lot 6 §4.4) → 404 (masquage non révélateur). */
final class ExchangeNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/exchange-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'exchange.error.not_found');
    }
}
