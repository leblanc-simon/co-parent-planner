<?php

declare(strict_types=1);

namespace App\Exception;

/** Mutation d'une note refusée : ni auteur ni parent/admin (§3.7). */
final class NoteForbiddenException extends AbstractProblemException
{
    public const string TYPE = '/errors/forbidden';

    public function __construct()
    {
        parent::__construct(403, self::TYPE, 'note.error.not_author');
    }
}
