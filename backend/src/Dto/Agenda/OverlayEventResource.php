<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use App\Service\Agenda\Occurrence;

/**
 * Projection **minimale en lecture seule** d'un événement d'une famille liée (Lot 5 §3.6). Point de
 * sécurité central : n'expose QUE l'horaire, la catégorie et le titre. **Aucun** champ enfant,
 * responsable, description, lieu, série ni identifiant mutable — anti-fuite garantie par l'absence
 * de ces propriétés (aucune clé correspondante n'est sérialisée). Objet non persistant, non éditable.
 */
final class OverlayEventResource
{
    public string $startsAt = '';
    public ?string $endsAt = null;
    public bool $allDay = false;
    public string $category = '';
    public string $title = '';
    /** Marqueur d'origine — toujours `linked` pour distinguer des événements propres (§3.5). */
    public string $source = 'linked';
    /** Couleur « famille liée » du design system §8. */
    public string $color = '#9C6FB0';
    /** Overlay strictement non éditable (RO). */
    public bool $editable = false;
    /** Identifiant de la famille liée (fourni par l'appelant via `overlay=`) — pour le regroupement UI. */
    public string $linkedFamilyId = '';

    /**
     * Projette une occurrence de la famille liée. `startsAt`/`endsAt` sont formatés dans le fuseau
     * de la **famille active** (`$tz`), l'expansion ayant déjà eu lieu dans le fuseau de la famille liée.
     */
    public static function fromOccurrence(Occurrence $occurrence, \DateTimeZone $tz, string $linkedFamilyId): self
    {
        $dto = new self();
        $dto->startsAt = $occurrence->startsAt->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->endsAt = $occurrence->endsAt?->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $dto->allDay = $occurrence->allDay;
        $dto->category = $occurrence->category->value;
        $dto->title = $occurrence->title;
        $dto->linkedFamilyId = $linkedFamilyId;

        return $dto;
    }
}
