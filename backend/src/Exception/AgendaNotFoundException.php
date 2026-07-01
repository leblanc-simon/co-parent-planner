<?php

declare(strict_types=1);

namespace App\Exception;

/** Événement introuvable ou hors scope famille (Lot 4 §4.5) → 404 (masquage non révélateur). */
final class AgendaNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/agenda-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'agenda.error.not_found');
    }
}
