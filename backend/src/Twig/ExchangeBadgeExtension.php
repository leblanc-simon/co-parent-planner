<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\ExchangeRepository;
use App\Web\ActiveFamilyResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit le compteur d'échanges ouverts de la famille active pour le badge de navigation (US-EX-7).
 * Rendu serveur à chaque chargement ; sur l'écran Échanges, il est maintenu en direct par Turbo Stream
 * (topic liste). Renvoie `null` si le membre n'a pas `exchanges ≥ view` (badge/onglet non rendus).
 */
final class ExchangeBadgeExtension extends AbstractExtension
{
    public function __construct(
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly ExchangeRepository $exchanges,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('exchanges_open_count', $this->openCount(...)),
        ];
    }

    public function openCount(): ?int
    {
        $active = $this->activeFamily->active($this->activeFamily->memberships());
        if (null === $active || !$active->can(RightDomain::Exchanges, RightLevel::View)) {
            return null;
        }

        return $this->exchanges->countOpen($active->getFamily());
    }
}
