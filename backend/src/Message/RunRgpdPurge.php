<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche une purge RGPD (§3.4). Planifiée en **live** (Scheduler) ; le mode transite en valeur
 * simple (RgpdMode) — aucune donnée personnelle dans la file.
 */
final readonly class RunRgpdPurge
{
    public function __construct(
        public string $mode = 'live',
    ) {
    }
}
