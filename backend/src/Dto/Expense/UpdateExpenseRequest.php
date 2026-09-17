<?php

declare(strict_types=1);

namespace App\Dto\Expense;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Modification d'une dépense (US-DEP-2 « marquer réglé », US-DEP-4 « corriger une saisie »).
 *
 * Sémantique **PATCH** : seuls les champs présents sont modifiés, d'où des propriétés toutes
 * nullables — `null` signifie « inchangé », jamais « effacer ». C'est ce qui permet à
 * `{"settled": true}` d'être une requête complète et légitime.
 *
 * `shares` non vide re-matérialise **tout** le partage : une part omise disparaît, sans quoi il
 * serait impossible de retirer un partage personnalisé.
 */
final class UpdateExpenseRequest
{
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    public ?string $category = null;

    public ?string $payerMembershipId = null;

    public ?int $amountCents = null;

    public ?string $expenseDate = null;

    public ?bool $settled = null;

    /** @var list<string>|null */
    public ?array $childIds = null;

    /** @var list<ShareInput>|null */
    public ?array $shares = null;
}
