<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\User;

/**
 * Calcul du report « quiet-hours » (Lot 7 §3.3). Les bornes `HH:MM` sont interprétées dans le fuseau
 * de la famille ; la plage peut chevaucher minuit. Le report cible la **fin de plage** en heure locale,
 * reconvertie en UTC à l'offset **de la cible** (DST géré par la lib de dates).
 */
final class QuietHours
{
    /**
     * Instant UTC auquel reporter la notification si `now` tombe dans la plage de silence, sinon
     * `null` (envoi immédiat).
     */
    public function deferUntil(User $user, \DateTimeImmutable $nowUtc, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $start = $user->getQuietHoursStart();
        $end = $user->getQuietHoursEnd();
        if (null === $start || null === $end || $start === $end) {
            return null; // pas de silence (ou plage vide)
        }

        $local = $nowUtc->setTimezone($tz);
        $startAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $local->format('Y-m-d').' '.$start, $tz);
        $endAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $local->format('Y-m-d').' '.$end, $tz);
        \assert($startAt instanceof \DateTimeImmutable && $endAt instanceof \DateTimeImmutable);

        if ($start < $end) {
            // Plage dans la même journée (ex. 01:00→06:00).
            $inRange = $local >= $startAt && $local < $endAt;
            $target = $endAt;
        } else {
            // Plage chevauchant minuit (ex. 22:00→07:00).
            $inRange = $local >= $startAt || $local < $endAt;
            $target = $local < $endAt ? $endAt : $endAt->modify('+1 day');
        }

        if (!$inRange) {
            return null;
        }

        return $target->setTimezone(new \DateTimeZone('UTC'));
    }
}
