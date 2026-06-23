<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « agenda » (cdc §2.2). */
final class AgendaVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::Agenda;
    }
}
