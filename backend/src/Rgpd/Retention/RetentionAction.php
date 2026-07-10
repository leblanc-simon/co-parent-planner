<?php

declare(strict_types=1);

namespace App\Rgpd\Retention;

/**
 * Action appliquée à une donnée expirée par la purge (cdc §7.1, Lot 8 §3.4).
 *
 * - anonymize : neutralise les champs personnels en préservant l'intégrité des fils (Anonymizer).
 * - delete    : suppression physique (cascade contrôlée, jamais le dernier admin).
 */
enum RetentionAction: string
{
    case Anonymize = 'anonymize';
    case Delete = 'delete';
}
