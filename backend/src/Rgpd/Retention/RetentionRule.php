<?php

declare(strict_types=1);

namespace App\Rgpd\Retention;

use Psr\Clock\ClockInterface;

/**
 * Règle de rétention **déclarative** (config versionnée, cdc §5.1 / §7.1). Pas de table métier :
 * les règles vivent dans {@see RetentionPolicy} (git), typées et testables.
 *
 * La durée est exprimée en jours civils : le décompte se fait dans le fuseau Europe/Paris (correct
 * malgré les changements d'heure, §4.1/§9, jamais de date codée en dur), puis la borne est **reconvertie
 * en UTC** pour être comparée sans décalage aux colonnes `datetime_immutable` (heure murale UTC, l'app
 * tournant en UTC). Sinon un offset systématique (+1/+2 h) fausserait la sélection.
 */
final readonly class RetentionRule
{
    public function __construct(
        public RetentionScope $scope,
        public int $durationDays,
        public RetentionAction $action,
        public bool $enabled,
        public string $label,
    ) {
    }

    /**
     * Instant d'expiration (UTC) : toute donnée dont la date d'ancrage est **antérieure** est expirée.
     */
    public function expiresBefore(ClockInterface $clock): \DateTimeImmutable
    {
        return $clock->now()
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->sub(new \DateInterval('P'.$this->durationDays.'D'))
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
