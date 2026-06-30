<?php

declare(strict_types=1);

namespace App\Exception;

/** L'invité / la cible est déjà membre actif de la famille (US-INV-1/3). */
final class AlreadyMemberException extends AbstractProblemException
{
    public const string TYPE = '/errors/already-member';

    public function __construct()
    {
        parent::__construct(409, self::TYPE, 'invitation.error.already_member');
    }
}
