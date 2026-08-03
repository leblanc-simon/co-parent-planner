<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Membership;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Web\ActiveFamilyResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose les **droits effectifs** du membre courant aux gabarits : `can('custody')`,
 * `can('agenda', 'edit')`.
 *
 * Pourquoi une extension et non une variable de contrôleur : le shell (barre latérale) est rendu
 * pour *toutes* les pages, y compris celles dont le contrôleur n'a aucune raison de résoudre les
 * droits. Il proposait donc les onglets Garde, Agenda et Enfants à des membres qui n'ont pas le
 * domaine — le clic menait à un écran de refus. Seul l'onglet Échanges se masquait, via
 * {@see ExchangeBadgeExtension} ; cette extension généralise ce comportement au lieu de le
 * dupliquer.
 *
 * L'appartenance active est résolue **une seule fois par requête** : la barre latérale appelle
 * `can()` plusieurs fois, et `ActiveFamilyResolver::memberships()` interroge la base.
 */
final class RightsExtension extends AbstractExtension
{
    private ?Membership $active = null;
    private bool $resolved = false;

    public function __construct(private readonly ActiveFamilyResolver $activeFamily)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can', $this->can(...)),
        ];
    }

    /**
     * Le membre courant possède-t-il au moins `$level` sur `$domain` ?
     *
     * Renvoie `false` hors contexte famille (visiteur anonyme, aucune famille) : un gabarit ne doit
     * jamais proposer une action que l'API refusera.
     *
     * @param string $domain valeur de {@see RightDomain} (`custody`, `agenda`, `children_notes`, …)
     * @param string $level  valeur de {@see RightLevel} (`view` par défaut, ou `edit`)
     */
    public function can(string $domain, string $level = 'view'): bool
    {
        $rightDomain = RightDomain::tryFrom($domain);
        $rightLevel = RightLevel::tryFrom($level);

        // Une faute de frappe dans un gabarit doit casser bruyamment : renvoyer `false` masquerait
        // silencieusement une section entière de l'application.
        if (null === $rightDomain || null === $rightLevel) {
            throw new \InvalidArgumentException(\sprintf('can(): domaine ou niveau de droit inconnu (« %s », « %s »).', $domain, $level));
        }

        return $this->activeMembership()?->can($rightDomain, $rightLevel) ?? false;
    }

    private function activeMembership(): ?Membership
    {
        if (!$this->resolved) {
            $this->active = $this->activeFamily->active($this->activeFamily->memberships());
            $this->resolved = true;
        }

        return $this->active;
    }
}
