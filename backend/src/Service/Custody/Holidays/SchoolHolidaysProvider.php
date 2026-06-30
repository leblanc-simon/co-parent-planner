<?php

declare(strict_types=1);

namespace App\Service\Custody\Holidays;

/**
 * Adaptateur isolé de la source officielle des vacances scolaires (cdc §4.7, Lot 3 §3.10).
 *
 * L'implémentation encapsule l'API `fr-en-calendrier-scolaire`, la pagination, le cache, la
 * conversion UTC→Europe/Paris et le filtrage de population. Un **double** la remplace en test
 * (aucun appel réseau en CI).
 */
interface SchoolHolidaysProvider
{
    /**
     * Périodes « élèves » de la zone/académie/année scolaire, normalisées (dates Europe/Paris).
     *
     * @return list<ImportedHoliday>
     *
     * @throws \RuntimeException si la source est indisponible/invalide (l'appelant gère le retry)
     */
    public function fetch(string $zone, string $academy, string $schoolYear): array;
}
