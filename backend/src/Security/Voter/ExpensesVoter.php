<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Enum\RightDomain;

/** Voter du domaine « dépenses » (v2, cdc §2.2). Toujours `none` pour un enfant ; inerte en v1. */
final class ExpensesVoter extends AbstractDomainVoter
{
    protected function domain(): RightDomain
    {
        return RightDomain::Expenses;
    }
}
