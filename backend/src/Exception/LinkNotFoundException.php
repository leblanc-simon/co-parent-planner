<?php

declare(strict_types=1);

namespace App\Exception;

/** Lien entre familles inexistant (US-LINK-2). */
final class LinkNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/link-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'familylink.error.not_found');
    }
}
