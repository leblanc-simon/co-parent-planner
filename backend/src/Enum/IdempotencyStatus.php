<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * État d'une entrée du store d'idempotence (§3.1).
 *
 * - in_progress : requête reçue, handler en cours (une concurrente identique → 409).
 * - completed   : réponse mémorisée (code + corps chiffré), rejouée à l'identique.
 */
enum IdempotencyStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
