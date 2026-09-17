<?php

declare(strict_types=1);

namespace App\Dto\Expense;

/** Solde signé d'un parent, en centimes : positif = on lui doit, négatif = il doit (§3.2). */
final class ExpenseNetResource
{
    public string $membershipId = '';
    public string $memberName = '';
    public int $netCents = 0;
}
