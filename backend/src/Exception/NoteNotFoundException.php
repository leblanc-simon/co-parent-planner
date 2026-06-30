<?php

declare(strict_types=1);

namespace App\Exception;

/** Note introuvable dans la famille active (résolution stricte, non révélateur — §4.4). */
final class NoteNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/note-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'note.error.not_found');
    }
}
