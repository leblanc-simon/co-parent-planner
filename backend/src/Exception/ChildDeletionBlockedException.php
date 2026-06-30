<?php

declare(strict_types=1);

namespace App\Exception;

/** Suppression d'un enfant refusée : profil rattaché à un compte-enfant (§4.2, US-CH-5). */
final class ChildDeletionBlockedException extends AbstractProblemException
{
    public const string TYPE = '/errors/child-deletion-blocked';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'child.error.deletion_blocked');
    }
}
