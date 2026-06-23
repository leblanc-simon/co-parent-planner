<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « notes enfants » (cdc §2.2). */
final class ChildrenNotesVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::ChildrenNotes;
    }
}
