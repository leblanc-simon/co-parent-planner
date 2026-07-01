<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie d'un événement d'agenda (cdc §2.5, §8 ; Lot 4 §0.2). Codes stables ; les libellés
 * affichés sont localisés (i18n). `sante` est un **libellé d'agenda**, jamais un champ typé de
 * données de santé (invariant « pas de données de santé » — Lot 4 §7.6).
 */
enum EventCategory: string
{
    case Sport = 'sport';
    case Musique = 'musique';
    case Ecole = 'ecole';
    case Sante = 'sante';
    case Autre = 'autre';
}
