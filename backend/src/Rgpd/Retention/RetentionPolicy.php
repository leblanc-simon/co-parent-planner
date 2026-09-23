<?php

declare(strict_types=1);

namespace App\Rgpd\Retention;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Politique de rétention **déclarative et versionnée** (décision Lot 8 : config plutôt que table).
 *
 * Source de vérité machine-lisible des durées de conservation, complémentaire du texte libre
 * `#[PersonalData]::retention` (documentaire) et de `docs/registre-donnees.md`. Les durées par défaut
 * sont surchargeables par variables d'environnement (déploiement) sans changement de code.
 *
 * Aucune date codée en dur : les seuils se dérivent de `now()` (cf. {@see RetentionRule::expiresBefore()}).
 */
final class RetentionPolicy
{
    /** @var list<RetentionRule> */
    private array $rules;

    private readonly int $exportsTtlHours;

    private readonly int $idempotencyTtlHours;

    private readonly int $dormantDevicesDays;

    public function __construct(
        #[Autowire('%env(int:RGPD_RETENTION_INACTIVE_ACCOUNTS_DAYS)%')]
        int $inactiveAccountsDays = 1095,
        #[Autowire('%env(int:RGPD_RETENTION_INVITATIONS_DAYS)%')]
        int $expiredInvitationsDays = 30,
        #[Autowire('%env(int:RGPD_RETENTION_EXPORTS_HOURS)%')]
        int $exportsTtlHours = 24,
        #[Autowire('%env(int:RGPD_RETENTION_AUDIT_LOGS_DAYS)%')]
        int $auditLogsDays = 1095,
        #[Autowire('%env(int:RGPD_RETENTION_NOTIFICATIONS_DAYS)%')]
        int $notificationsDays = 90,
        #[Autowire('%env(int:RGPD_RETENTION_IDEMPOTENCY_HOURS)%')]
        int $idempotencyTtlHours = 48,
        #[Autowire('%env(int:RGPD_RETENTION_DEVICE_TOKENS_DAYS)%')]
        int $retiredDevicesDays = 30,
        #[Autowire('%env(int:RGPD_RETENTION_DORMANT_DEVICES_DAYS)%')]
        int $dormantDevicesDays = 270,
    ) {
        // Un jeton FCM resté muet ~270 jours est tenu pour périmé par Google lui-même : le garder
        // n'apporte plus de joignabilité, seulement de la surface (Lot 11 §4.8).
        $this->dormantDevicesDays = max(1, $dormantDevicesDays);
        // Les exports (dump complet de PII) ont un TTL court exprimé en HEURES : le Purger l'applique
        // à l'heure près (pas de quantification en jours) afin de ne pas sur-conserver la donnée la plus
        // sensible et d'honorer une valeur sub-journalière (minimisation §3.4).
        $this->exportsTtlHours = max(1, $exportsTtlHours);
        $this->idempotencyTtlHours = max(1, $idempotencyTtlHours);
        // `durationDays` des règles à TTL horaire (exports, idempotence) reste indicatif ; la borne
        // effective vient des accesseurs *TtlHours() (cf. Purger).
        $indicativeExportDays = (int) max(1, ceil($this->exportsTtlHours / 24));
        $indicativeIdempotencyDays = (int) max(1, ceil($this->idempotencyTtlHours / 24));

        $this->rules = [
            new RetentionRule(RetentionScope::InactiveAccounts, $inactiveAccountsDays, RetentionAction::Anonymize, true, 'Comptes inactifs'),
            new RetentionRule(RetentionScope::ExpiredInvitations, $expiredInvitationsDays, RetentionAction::Delete, true, 'Invitations expirées'),
            new RetentionRule(RetentionScope::ExpiredExports, $indicativeExportDays, RetentionAction::Delete, true, 'Exports temporaires expirés'),
            new RetentionRule(RetentionScope::AuditLogs, $auditLogsDays, RetentionAction::Delete, true, 'Journaux d\'audit'),
            new RetentionRule(RetentionScope::NotificationDispatches, $notificationsDays, RetentionAction::Delete, true, 'Journaux de notifications'),
            new RetentionRule(RetentionScope::OneTimeTokens, 1, RetentionAction::Delete, true, 'Jetons à usage unique expirés'),
            new RetentionRule(RetentionScope::IdempotencyKeys, $indicativeIdempotencyDays, RetentionAction::Delete, true, 'Clés d\'idempotence'),
            new RetentionRule(RetentionScope::DeviceTokens, $retiredDevicesDays, RetentionAction::Delete, true, 'Jetons push révoqués, invalides ou dormants'),
        ];
    }

    /** Silence au-delà duquel un appareil encore « actif » est purgé (Lot 11 §4.8). */
    public function dormantDevicesDays(): int
    {
        return $this->dormantDevicesDays;
    }

    /** TTL des artefacts d'export, en heures (borne appliquée à l'heure près par le Purger). */
    public function exportsTtlHours(): int
    {
        return $this->exportsTtlHours;
    }

    /** TTL des clés d'idempotence, en heures (§6.3 ; borne appliquée à l'heure près). */
    public function idempotencyTtlHours(): int
    {
        return $this->idempotencyTtlHours;
    }

    /** @return list<RetentionRule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return list<RetentionRule> règles actives uniquement (les autres sont ignorées par la purge). */
    public function enabledRules(): array
    {
        return array_values(array_filter($this->rules, static fn (RetentionRule $r): bool => $r->enabled));
    }
}
