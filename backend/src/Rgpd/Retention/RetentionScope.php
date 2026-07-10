<?php

declare(strict_types=1);

namespace App\Rgpd\Retention;

/**
 * Périmètre ciblé par une règle de rétention (Lot 8 §3.4). Chaque scope est traité par le Purger
 * via une stratégie idempotente dédiée (ne sélectionne que des cibles « encore expirées »).
 */
enum RetentionScope: string
{
    /** Comptes inactifs au-delà de la durée → anonymisation (hors dernier admin, signalé en écart). */
    case InactiveAccounts = 'inactive_accounts';
    /** Invitations expirées/en attente au-delà de la durée → suppression. */
    case ExpiredInvitations = 'expired_invitations';
    /** Artefacts d'export temporaires au-delà du TTL du lien signé → suppression (fichier + réf). */
    case ExpiredExports = 'expired_exports';
    /** Entrées AuditLog au-delà de la rétention des journaux → suppression. */
    case AuditLogs = 'audit_logs';
    /** Clés d'idempotence au-delà du TTL borné (§6.3) → suppression. */
    case IdempotencyKeys = 'idempotency_keys';
    /** Journaux d'envoi de notifications au-delà de la rétention → suppression. */
    case NotificationDispatches = 'notification_dispatches';
    /** Jetons à usage unique expirés (vérif e-mail, reset, step-up) → suppression. */
    case OneTimeTokens = 'one_time_tokens';
}
