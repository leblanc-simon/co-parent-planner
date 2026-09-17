<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Dépense introuvable, hors scope famille, **ou module Dépenses désactivé** (Lot 10 §3.4/§4.5) → 404.
 *
 * Le même statut couvre les trois cas à dessein : répondre 403 sur un module désactivé révélerait
 * l'existence de la ressource, et distinguer « hors famille » de « inexistant » ouvrirait une
 * énumération des identifiants (anti-IDOR).
 */
final class ExpenseNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/expense-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'expense.error.not_found');
    }
}
