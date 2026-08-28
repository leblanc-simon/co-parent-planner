<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyException;
use App\Entity\Membership;
use App\Entity\SchoolHoliday;
use App\Enum\CustodyDaySource;

/**
 * Détail d'un jour de garde prêt pour le rendu (US-CUST-6) : chez quel parent, d'où vient
 * l'attribution, et quand se fait la prochaine transmission. VO de présentation — rien n'est
 * persisté ici, tout est dérivé des `CustodyDay` matérialisés et des périodes de la famille.
 *
 * Les activités du jour n'y figurent pas : elles relèvent du domaine de droit `agenda`, résolu
 * séparément par l'appelant (un membre peut avoir `custody` sans `agenda`).
 */
final class DayDetail
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        /** Parent chez qui sont les enfants ; null si le jour n'est pas (encore) matérialisé. */
        public readonly ?Membership $parent,
        public readonly ?CustodyDaySource $source,
        /** Exception couvrant ce jour, si l'attribution en vient (porte la raison saisie). */
        public readonly ?CustodyException $exception,
        /** Période de vacances scolaires couvrant ce jour, qu'elle porte une règle ou non. */
        public readonly ?SchoolHoliday $holiday,
        /** Date du prochain changement d'attributaire ; null si aucun n'est matérialisé à horizon. */
        public readonly ?\DateTimeImmutable $handoverDate,
        /** Parent qui prend le relais à cette date. */
        public readonly ?Membership $handoverParent,
        /** Heure d'échange du rythme (`HH:MM`), null sans rythme configuré. */
        public readonly ?string $handoverTime,
        /**
         * Jour antérieur à aujourd'hui : l'API refuse d'y créer une exception (422, §3.3), donc
         * l'action « Échanger ce jour » doit être présentée désactivée plutôt que d'échouer.
         */
        public readonly bool $isPast,
    ) {
    }
}
