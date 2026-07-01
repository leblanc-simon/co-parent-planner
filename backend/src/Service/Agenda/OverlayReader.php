<?php

declare(strict_types=1);

namespace App\Service\Agenda;

use App\Dto\Agenda\OverlayEventResource;
use App\Entity\Family;
use App\Entity\FamilyLink;
use App\Enum\EventCategory;
use App\Exception\OverlayLinkInvalidException;
use App\Repository\FamilyLinkRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Overlay des familles liées (Lot 5 §3.2/§3.3) : résout les liens **actifs** désignés par `overlay=`
 * et projette leurs événements en lecture seule (§3.6). L'habilitation `agenda:view` est vérifiée en
 * amont par l'appelant (scope famille active) ; ici on ne valide que la **liaison active**.
 *
 * Politique multi-valeurs (§4.4) : chaque id est validé indépendamment ; les non-actifs sont ignorés.
 * Si des ids sont demandés mais qu'**aucun** n'est un lien actif → 422 uniforme (non-énumération).
 */
final class OverlayReader
{
    public function __construct(
        private readonly FamilyLinkRepository $links,
        private readonly AgendaReader $reader,
    ) {
    }

    /**
     * @return list<FamilyLink> liens actifs valides (dédupliqués)
     *
     * @throws OverlayLinkInvalidException si des ids sont demandés mais aucun n'est un lien actif
     */
    public function resolveLinks(Family $family, ?string $overlayParam): array
    {
        if (null === $overlayParam || '' === trim($overlayParam)) {
            return [];
        }

        $requested = array_values(array_filter(
            array_map('trim', explode(',', $overlayParam)),
            static fn (string $id): bool => '' !== $id,
        ));
        if ([] === $requested) {
            return [];
        }

        /** @var array<string, FamilyLink> $valid */
        $valid = [];
        foreach ($requested as $id) {
            if (!Uuid::isValid($id)) {
                continue;
            }
            $link = $this->links->findActiveBetween($family, Uuid::fromString($id));
            if (null !== $link) {
                // Dédup par identité du lien (pas la chaîne brute) : deux formes du même UUID
                // (casse/encodage) désignent le même lien et ne doivent pas doubler les événements.
                $valid[(string) $link->getId()] = $link;
            }
        }

        if ([] === $valid) {
            throw new OverlayLinkInvalidException();
        }

        return array_values($valid);
    }

    /**
     * Événements projetés (RO) des familles liées actives sur [from, to].
     *
     * @param list<EventCategory>|null $categories
     *
     * @return list<OverlayEventResource>
     */
    public function overlayEvents(
        Family $family,
        ?string $overlayParam,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?array $categories,
    ): array {
        $tz = new \DateTimeZone($family->getTimezone());
        $out = [];
        foreach ($this->resolveLinks($family, $overlayParam) as $link) {
            $other = $link->otherFamily($family);
            $linkedId = (string) $other->getId();
            // Expansion dans le fuseau de la famille liée (AgendaReader), affichage dans celui de A.
            foreach ($this->reader->occurrences($other, $from, $to, $categories) as $occurrence) {
                $out[] = OverlayEventResource::fromOccurrence($occurrence, $tz, $linkedId);
            }
        }

        return $out;
    }
}
