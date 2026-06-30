<?php

declare(strict_types=1);

namespace App\Exception;

/** Lecture de garde inexistante (famille unie → masquage fonctionnel 404, ou ressource hors scope). */
final class CustodyNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/custody-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'custody.error.not_found');
    }
}
