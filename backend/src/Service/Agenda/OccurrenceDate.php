<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Exception\AgendaInputException;

/**
 * Lecture d'une **date d'occurrence** (`recurrenceDate`, `from`) : identifie une occurrence de série
 * par sa date d'origine, dans le fuseau de la famille.
 *
 * Accepte la date seule (`2026-09-17`) **et** l'instant ISO 8601 que l'API elle-même renvoie
 * (`2026-09-17T17:00:00+02:00`) : n'accepter que la première forme envoyait un client qui réutilisait
 * le `startsAt` reçu dans une impasse — un 422 « La date ne correspond à aucune occurrence de la
 * série. » qui affirmait que l'occurrence n'existait pas alors que seul le format était en cause
 * (revue 2026-08 C9). Un format vraiment invalide a désormais son propre message, distinct de
 * l'occurrence introuvable.
 *
 * L'instant est ramené à la date **calendaire du fuseau famille** — même convention que le reste du
 * moteur (jamais de comparaison en instants).
 */
final class OccurrenceDate
{
    /** Date seule, ou date + heure avec offset facultatif. Ferme la porte aux formats relatifs
     *  que `DateTimeImmutable` accepterait volontiers (« tomorrow », « +1 week »). */
    private const string ACCEPTED = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    public static function parse(?string $raw, \DateTimeZone $tz): \DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            throw new AgendaInputException('agenda.error.occurrence_date_required');
        }
        if (1 !== preg_match(self::ACCEPTED, $raw)) {
            throw new AgendaInputException('agenda.error.occurrence_date_invalid');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
        if (false !== $date && $date->format('Y-m-d') === $raw) {
            return $date;
        }

        try {
            $instant = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            throw new AgendaInputException('agenda.error.occurrence_date_invalid');
        }

        // Une chaîne sans offset (`2026-09-17T17:00`) a été interprétée dans le fuseau du serveur :
        // on ne conserve que la date locale famille, ce qui est précisément la clé d'occurrence.
        return new \DateTimeImmutable($instant->setTimezone($tz)->format('Y-m-d').' 00:00:00', $tz);
    }
}
