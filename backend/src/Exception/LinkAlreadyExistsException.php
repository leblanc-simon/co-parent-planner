<?php

declare(strict_types=1);

namespace App\Exception;

/** Un lien existe déjà pour la paire de familles, quel que soit le sens (§3.6). */
final class LinkAlreadyExistsException extends AbstractProblemException
{
    public const string TYPE = '/errors/link-already-exists';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'familylink.error.already_exists');
    }
}
