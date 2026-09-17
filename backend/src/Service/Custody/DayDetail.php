<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Entity\CustodyException;
use App\Entity\Membership;
use App\Entity\SchoolHoliday;
use App\Enum\CustodyDaySource;

/**
 * Détail d'un jour de garde prêt pour le rendu (US-CUST-6) : chez quel parent et à quelles heures,
 * d'où vient l'attribution, et quand se fait la prochaine transmission. VO de présentation — rien
 * n'est persisté ici, tout est dérivé des `CustodyDay` matérialisés et des périodes de la famille.
 *
 * Les activités du jour n'y figurent pas : elles relèvent du domaine de droit `agenda`, résolu
 * séparément par l'appelant (un membre peut avoir `custody` sans `agenda`).
 */
final class DayDetail
{
    public function __construct(
        public readonly \DateTimeImmutable $date,
        /**
         * Parent **majoritaire** de la journée — celui à qui elle est comptée, dont la case porte
         * l'initiale. Null si le jour n'est pas (encore) matérialisé.
         */
        public readonly ?Membership $parent,
        /**
         * Tranches horaires de la journée, dans l'ordre, parents résolus : une seule pour une
         * journée unie, deux quand la transmission tombe dedans, trois quand un échange partiel est
         * encadré par le rythme. Vide si le jour n'est pas matérialisé.
         *
         * @var list<array{from: string, to: string, parent: ?Membership, source: CustodyDaySource}>
         */
        public readonly array $segments,
        public readonly ?CustodyDaySource $source,
        /** Exception couvrant ce jour, si l'attribution en vient (porte la raison saisie). */
        public readonly ?CustodyException $exception,
        /** Période de vacances scolaires couvrant ce jour, qu'elle porte une règle ou non. */
        public readonly ?SchoolHoliday $holiday,
        /** Date de la prochaine transmission ; null si aucune n'est matérialisée à horizon. */
        public readonly ?\DateTimeImmutable $handoverDate,
        /** Parent qui prend le relais à ce moment-là. */
        public readonly ?Membership $handoverParent,
        /** Heure de cette transmission (`HH:MM`) — celle du rythme, ou celle d'un échange. */
        public readonly ?string $handoverTime,
        /**
         * Jour antérieur à aujourd'hui : l'API refuse d'y créer une exception (422, §3.3), donc
         * l'action « Échanger ce jour » doit être présentée désactivée plutôt que d'échouer.
         */
        public readonly bool $isPast,
        /**
         * Bornes proposées par « Échanger ce jour » et parent qui récupérerait la journée. Null
         * quand l'échange n'a pas de sens (jour passé, pas de rythme, un seul parent connu).
         */
        public readonly ?SwapDefaults $swapDefaults = null,
        public readonly ?Membership $swapTarget = null,
    ) {
    }

    /** La journée change de parent en cours de route : la case porte deux (ou trois) couleurs. */
    public function isShared(): bool
    {
        return \count($this->segments) > 1;
    }

    /**
     * Exception **annulable** depuis la fiche : elle donne l'attribution d'au moins une tranche du
     * jour et ne déborde pas au-delà de la veille et du lendemain.
     *
     * Ré-échanger un jour déjà échangé doit défaire l'échange — retour au rythme habituel ou à la
     * règle de vacances — et non empiler une seconde exception en sens inverse : la fiche propose
     * donc « Annuler l'échange » plutôt que « Échanger ce jour ». Une exception de séjour, qui
     * couvre d'autres dates, n'est pas concernée : la défaire ici toucherait des jours que
     * l'utilisateur ne regarde pas (elle se retire depuis les réglages).
     */
    public function revertibleException(): ?CustodyException
    {
        if (null === $this->exception || CustodyDaySource::Exception !== $this->source || $this->isPast) {
            return null;
        }

        $first = $this->date->modify('-1 day')->format('Y-m-d');
        $last = $this->date->modify('+1 day')->format('Y-m-d');

        return $this->exception->getStartDate()->format('Y-m-d') >= $first
            && $this->exception->getEndDate()->format('Y-m-d') <= $last
            ? $this->exception
            : null;
    }
}
