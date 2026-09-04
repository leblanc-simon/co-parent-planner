<?php

declare(strict_types=1);

namespace App\Web;

/**
 * Libellés de date du front web, en français.
 *
 * L'interface web est **livrée en français uniquement** (divergence assumée, Lot 5) : elle ne passe
 * pas par le catalogue de traduction, réservé aux textes que l'API et les e-mails renvoient. Ces
 * tables vivaient jusqu'ici en quatre exemplaires, copiées dans `AgendaController` et
 * `GardeController` — et une cinquième copie s'apprêtait à naître dans `HomeController`.
 */
final class FrenchDate
{
    private const array DAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const array MONTHS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** « dimanche 28 juin 2026 » — date isolée, dont le millésime n'est pas déductible. */
    public static function longDate(\DateTimeInterface $date): string
    {
        return self::dayMonth($date).' '.$date->format('Y');
    }

    /** « dimanche 28 juin » — date proche, lue dans le contexte du mois affiché. */
    public static function dayMonth(\DateTimeInterface $date): string
    {
        return self::DAYS[(int) $date->format('w')].' '.$date->format('j').' '.self::MONTHS[(int) $date->format('n')];
    }

    /** « juin 2026 » — en-tête de période. */
    public static function month(\DateTimeInterface $date): string
    {
        return self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');
    }
}
