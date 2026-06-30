<?php

declare(strict_types=1);

namespace App\Service\Child;

use Psr\Clock\ClockInterface;

/**
 * Calcule l'âge en années révolues à partir d'une date de naissance (Lot 2 §3.1).
 *
 * Calcul en **composantes calendaires locales** (fuseau famille, défaut Europe/Paris) via une
 * horloge injectable — jamais `new \DateTime()` en dur, jamais la date des maquettes. Insensible
 * au DST (on compare des dates, pas des instants).
 */
final class AgeCalculator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function age(?\DateTimeImmutable $birthDate, string $timezone = 'Europe/Paris'): ?int
    {
        if (null === $birthDate) {
            return null;
        }

        // « Aujourd'hui » dans le fuseau famille. La date de naissance est une date CALENDAIRE :
        // on la lit telle quelle (format), sans conversion de fuseau qui la décalerait d'un jour
        // pour les fuseaux à l'ouest d'UTC (la colonne `date` est stockée à minuit UTC).
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($timezone));
        $todayYmd = $today->format('Y-m-d');
        $birthYmd = $birthDate->format('Y-m-d');

        // Date de naissance future : défense (devrait être bloquée en validation).
        if ($birthYmd > $todayYmd) {
            return null;
        }

        $age = (int) substr($todayYmd, 0, 4) - (int) substr($birthYmd, 0, 4);
        // Anniversaire pas encore passé cette année → -1 (comparaison m-d).
        if (substr($todayYmd, 5) < substr($birthYmd, 5)) {
            --$age;
        }

        return $age;
    }
}
