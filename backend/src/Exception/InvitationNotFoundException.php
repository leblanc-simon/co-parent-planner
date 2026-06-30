<?php

declare(strict_types=1);

namespace App\Exception;

/** Invitation introuvable dans la famille active (anti-énumération — §4.8). */
final class InvitationNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/invitation-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'invitation.error.not_found');
    }
}
