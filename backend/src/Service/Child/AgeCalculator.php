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

        $tz = new \DateTimeZone($timezone);
        $today = $this->clock->now()->setTimezone($tz);
        $birth = $birthDate->setTimezone($tz);

        // Date de naissance future : défense (devrait être bloquée en validation).
        if ($birth->format('Y-m-d') > $today->format('Y-m-d')) {
            return null;
        }

        $age = (int) $today->format('Y') - (int) $birth->format('Y');
        // Anniversaire pas encore passé cette année → -1.
        if ($today->format('m-d') < $birth->format('m-d')) {
            --$age;
        }

        return $age;
    }
}
