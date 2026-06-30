<?php

declare(strict_types=1);

namespace App\Service\Custody;

/**
 * Arithmétique de dates calendaires pour le moteur de garde (Lot 3 §3.2).
 * Convention de jour de semaine figée (§4.5) : **0 = dimanche … 6 = samedi** (= `date('w')`).
 * Aucune dépendance au fuseau pour l'arithmétique (on raisonne en jours calendaires).
 */
final class CustodyDateMath
{
    /** Jour de semaine 0=dim … 6=sam. */
    public static function weekday(\DateTimeImmutable $d): int
    {
        return (int) $d->format('w');
    }

    /**
     * Nombre de jours **calendaires** de `$from` à `$to` (signé). On compare les dates calendaires
     * (Y-m-d de chaque argument dans SON fuseau), jamais les instants : mélanger des minuits de
     * fuseaux différents (ex. anchor UTC vs aujourd'hui Europe/Paris) fausserait `diff->days` d'un jour.
     */
    public static function daysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $utc = new \DateTimeZone('UTC');
        $a = \DateTimeImmutable::createFromFormat('!Y-m-d', $from->format('Y-m-d'), $utc);
        $b = \DateTimeImmutable::createFromFormat('!Y-m-d', $to->format('Y-m-d'), $utc);
        \assert(false !== $a && false !== $b);
        $diff = $a->diff($b);

        return (int) $diff->days * (1 === $diff->invert ? -1 : 1);
    }

    /** Dernier `$handoverWeekday` (0..6) à ou avant `$anchor` — aligne les bascules sur l'échange. */
    public static function cycleStart(\DateTimeImmutable $anchor, int $handoverWeekday): \DateTimeImmutable
    {
        $delta = (self::weekday($anchor) - $handoverWeekday + 7) % 7;

        return $anchor->setTime(0, 0)->modify("-{$delta} days");
    }
}
