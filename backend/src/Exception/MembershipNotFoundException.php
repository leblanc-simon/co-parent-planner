<?php

declare(strict_types=1);

namespace App\Exception;

/** Membership introuvable dans la famille active (anti-énumération cross-family — §4.3). */
final class MembershipNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/membership-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'membership.error.not_found');
    }
}
