<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\CustodyPalette;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\CustodyColorPreferenceRepository;
use App\Repository\CustodyPatternRepository;
use App\Web\ActiveFamilyResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Pose les couleurs du planning de garde **du spectateur** sous forme de variables CSS (cdc §8.2,
 * évolution E5).
 *
 * Les feuilles de style lisent `--slot-a` / `--slot-b` (et leurs déclinaisons) au lieu des tokens
 * `--accent` / `--positive` : sans préférence enregistrée, les variables valent les deux teintes
 * par défaut du §8 et l'écran est exactement celui d'avant. Rendu **côté serveur**, dans le HTML :
 * la page mise en cache par le service worker embarque donc déjà ses couleurs, et le planning ne
 * change pas d'apparence en mode avion (exigence hors-ligne de l'évolution B).
 *
 * Posé sur le conteneur applicatif — donc valable pour l'accueil, la garde, le fragment de fiche de
 * jour chargé dans son `<turbo-frame>` et l'aperçu des pastilles de la page Paramètres.
 */
final class CustodyPaletteExtension extends AbstractExtension
{
    /**
     * Résolution mémoïsée : la page Paramètres appelle les deux fonctions, et le rythme comme les
     * préférences ne changent pas au cours d'un rendu.
     *
     * @var array{A: CustodyPalette, B: CustodyPalette}|null
     */
    private ?array $resolved = null;
    private bool $didResolve = false;

    public function __construct(
        private readonly ActiveFamilyResolver $activeFamily,
        private readonly CustodyPatternRepository $patterns,
        private readonly CustodyColorPreferenceRepository $preferences,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('custody_palette_style', $this->style(...)),
            new TwigFunction('custody_palette_catalog', $this->catalog(...)),
        ];
    }

    /**
     * Déclarations CSS à poser en attribut `style`, ou chaîne vide s'il n'y a rien à personnaliser.
     *
     * Rien n'est émis sans droit `custody ≥ view` : les variables ne seraient de toute façon lues
     * par aucune surface, et le membre n'a pas à recevoir un indice sur le rythme de la famille.
     */
    public function style(): string
    {
        $palette = $this->current();
        if (null === $palette) {
            return '';
        }

        $declarations = '';
        foreach (['a' => $palette['A'], 'b' => $palette['B']] as $slot => $colors) {
            $c = $colors->colors();
            $declarations .= \sprintf(
                '--slot-%1$s:%2$s;--slot-%1$s-soft:%3$s;--slot-%1$s-ink:%4$s;',
                $slot,
                $c['border'],
                $c['soft'],
                $c['ink'],
            );
        }

        return $declarations;
    }

    /**
     * Catalogue des huit teintes + la teinte retenue pour chaque parent, pour l'écran de choix.
     *
     * @return array{palette: list<array{value: string, label: string, border: string, soft: string, ink: string}>, current: array{A: string, B: string}}|null
     */
    public function catalog(): ?array
    {
        $current = $this->current();
        if (null === $current) {
            return null;
        }

        return [
            'palette' => array_map(
                static fn (CustodyPalette $c): array => ['value' => $c->value, 'label' => $c->label()] + $c->colors(),
                CustodyPalette::cases(),
            ),
            'current' => ['A' => $current['A']->value, 'B' => $current['B']->value],
        ];
    }

    /** @return array{A: CustodyPalette, B: CustodyPalette}|null */
    private function current(): ?array
    {
        if ($this->didResolve) {
            return $this->resolved;
        }
        $this->didResolve = true;

        $active = $this->activeFamily->active($this->activeFamily->memberships());
        if (null === $active
            || !$active->getFamily()->getType()->hasCustody()
            || !$active->can(RightDomain::Custody, RightLevel::View)) {
            return null;
        }

        $pattern = $this->patterns->findOneByFamily($active->getFamily());

        return $this->resolved = $this->preferences->paletteFor($active, $pattern?->getParentA(), $pattern?->getParentB());
    }
}
