<?php

declare(strict_types=1);

namespace App\Rgpd;

use App\Enum\RgpdMode;
use App\Repository\AuditLogRepository;
use App\Repository\IdempotencyKeyRepository;
use App\Repository\InvitationRepository;
use App\Repository\NotificationDispatchRepository;
use App\Repository\OneTimeTokenRepository;
use App\Repository\RgpdOperationRepository;
use App\Repository\UserRepository;
use App\Rgpd\Export\ExportStorage;
use App\Rgpd\Retention\RetentionPolicy;
use App\Rgpd\Retention\RetentionRule;
use App\Rgpd\Retention\RetentionScope;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Purge planifiée idempotente (Lot 8 §3.4). En **dry_run** (défaut), calcule ce qui SERAIT
 * supprimé/anonymisé sans rien modifier ; en **live**, applique. Ne sélectionne que des cibles
 * « encore expirées » → relancer ne re-traite rien (résultat 0). Bornes calculées par rapport à
 * `now()` (Europe/Paris), jamais de date codée en dur.
 *
 * Garde-fous : le dernier admin de famille n'est jamais anonymisé (exclu + signalé en écart) ;
 * les admins techniques inactifs sont exclus par le repository.
 */
final class Purger
{
    public function __construct(
        private readonly RetentionPolicy $policy,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly InvitationRepository $invitations,
        private readonly AuditLogRepository $auditLogs,
        private readonly NotificationDispatchRepository $dispatches,
        private readonly OneTimeTokenRepository $oneTimeTokens,
        private readonly IdempotencyKeyRepository $idempotencyKeys,
        private readonly RgpdOperationRepository $operations,
        private readonly ExportStorage $storage,
        private readonly Anonymizer $anonymizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function run(RgpdMode $mode): PurgeReport
    {
        $lines = [];
        foreach ($this->policy->enabledRules() as $rule) {
            $before = $rule->expiresBefore($this->clock);
            $lines[] = match ($rule->scope) {
                RetentionScope::InactiveAccounts => $this->purgeInactiveAccounts($mode, $before, $rule),
                RetentionScope::ExpiredInvitations => $this->count($mode, $rule,
                    fn (): int => $this->invitations->countExpiredBefore($before),
                    fn (): int => $this->invitations->deleteExpiredBefore($before)),
                // Exports : borne à l'heure près (TTL en heures), pas la borne jour de $rule.
                RetentionScope::ExpiredExports => $this->purgeExports(
                    $mode,
                    $this->clock->now()->sub(new \DateInterval('PT'.$this->policy->exportsTtlHours().'H')),
                    $rule,
                ),
                RetentionScope::AuditLogs => $this->count($mode, $rule,
                    fn (): int => $this->auditLogs->countOlderThan($before),
                    fn (): int => $this->auditLogs->deleteOlderThan($before)),
                RetentionScope::NotificationDispatches => $this->count($mode, $rule,
                    fn (): int => $this->dispatches->countOlderThan($before),
                    fn (): int => $this->dispatches->deleteOlderThan($before)),
                RetentionScope::OneTimeTokens => $this->count($mode, $rule,
                    fn (): int => $this->oneTimeTokens->countExpiredBefore($before),
                    fn (): int => $this->oneTimeTokens->deleteExpiredBefore($before)),
                RetentionScope::IdempotencyKeys => $this->purgeIdempotencyKeys($mode, $rule),
            };
        }

        return new PurgeReport($mode->value, $lines);
    }

    /**
     * Scope à comptage simple : dry-run = count, live = delete (idempotent).
     *
     * @param callable(): int $count
     * @param callable(): int $delete
     */
    private function count(RgpdMode $mode, RetentionRule $rule, callable $count, callable $delete): PurgeReportLine
    {
        $candidates = $count();
        $processed = RgpdMode::Live === $mode ? $delete() : 0;

        return new PurgeReportLine(
            $rule->scope->value, $rule->label, $rule->action->value, $rule->durationDays,
            $candidates, $processed, 0,
        );
    }

    private function purgeInactiveAccounts(RgpdMode $mode, \DateTimeImmutable $before, RetentionRule $rule): PurgeReportLine
    {
        $candidates = $this->users->findInactiveCandidates($before);
        $processed = 0;
        $skipped = 0;

        foreach ($candidates as $user) {
            // Garde-fou dernier admin : jamais anonymisé, signalé en écart de conformité.
            if ($this->anonymizer->isBlockedByLastAdmin($user)) {
                ++$skipped;
                continue;
            }
            if (RgpdMode::Live === $mode) {
                try {
                    $this->anonymizer->anonymize($user, null, null);
                    ++$processed;
                } catch (\Throwable $e) {
                    // Isolation par compte : un échec (course sur le dernier admin, erreur de
                    // persistance…) est signalé en écart et ne fait PAS avorter le run (ni sa
                    // traçabilité RgpdOperation/AuditLog), ni la boucle sur les autres candidats.
                    ++$skipped;
                    $this->logger->warning('Purge : anonymisation du compte inactif échouée', [
                        'user' => (string) $user->getId(),
                        'error' => $e::class,
                    ]);
                }
            }
        }

        return new PurgeReportLine(
            $rule->scope->value, $rule->label, $rule->action->value, $rule->durationDays,
            \count($candidates), $processed, $skipped,
        );
    }

    private function purgeIdempotencyKeys(RgpdMode $mode, RetentionRule $rule): PurgeReportLine
    {
        $before = $this->clock->now()->sub(new \DateInterval('PT'.$this->policy->idempotencyTtlHours().'H'));

        return $this->count($mode, $rule,
            fn (): int => $this->idempotencyKeys->countOlderThan($before),
            fn (): int => $this->idempotencyKeys->deleteOlderThan($before));
    }

    private function purgeExports(RgpdMode $mode, \DateTimeImmutable $before, RetentionRule $rule): PurgeReportLine
    {
        $expired = $this->operations->findExpiredExports($before);
        $processed = 0;

        if (RgpdMode::Live === $mode) {
            foreach ($expired as $operation) {
                $ref = $operation->getResultRef();
                if (null !== $ref) {
                    $this->storage->delete($ref);
                    $operation->setResultRef(null);
                    ++$processed;
                }
            }
            $this->em->flush();
        }

        return new PurgeReportLine(
            $rule->scope->value, $rule->label, $rule->action->value, $rule->durationDays,
            \count($expired), $processed, 0,
        );
    }
}
