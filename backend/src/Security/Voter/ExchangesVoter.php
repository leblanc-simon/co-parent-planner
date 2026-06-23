<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « échanges » (cdc §2.2). */
final class ExchangesVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::Exchanges;
    }
}
