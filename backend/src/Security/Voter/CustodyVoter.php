<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « planning de garde » (cdc §2.2). */
final class CustodyVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::Custody;
    }
}
