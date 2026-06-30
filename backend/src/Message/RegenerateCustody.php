<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Demande de (re)génération de l'horizon de garde d'une famille (Lot 3 §3.5), traitée en asynchrone
 * par un worker. Le handler est **idempotent** (upsert + passé figé) ; rejouer est sans effet de bord.
 */
final readonly class RegenerateCustody
{
    public function __construct(public string $familyId)
    {
    }
}
