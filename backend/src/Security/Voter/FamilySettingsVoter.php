<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « réglages famille » (cdc §2.2). Édition réservée aux admins. */
final class FamilySettingsVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::FamilySettings;
    }
}
