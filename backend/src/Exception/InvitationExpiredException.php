<?php

declare(strict_types=1);

namespace App\Exception;

/** Invitation expirée (échéance dépassée) — refus d'acceptation tardive (§3.3 C.5). */
final class InvitationExpiredException extends AbstractProblemException
{
    public const string TYPE = '/errors/invitation-expired';

    public function __construct()
    {
        parent::__construct(410, self::TYPE, 'invitation.error.expired');
    }
}
