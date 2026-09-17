<?php

declare(strict_types=1);

namespace App\Twig;

use App\Web\Money;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtre `|money` : rend des centimes entiers dans la locale de l'application (Lot 10 §4.10).
 *
 * Passe par un filtre Twig plutôt que par un champ pré-formaté du DTO : le contrat API transporte
 * des centimes (le mobile a son propre formateur), et dupliquer la mise en forme côté serveur ferait
 * diverger les deux à la première évolution.
 */
final class MoneyExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private readonly string $locale,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
        ];
    }

    public function money(int $cents, string $currency = 'EUR'): string
    {
        return Money::format($cents, $this->locale, $currency);
    }
}
