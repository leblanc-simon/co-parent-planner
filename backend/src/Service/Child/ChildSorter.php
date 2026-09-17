<?php

declare(strict_types=1);

namespace App\Service\Child;

use App\Entity\Child;

/**
 * Tri alphabétique des enfants d'une famille, **en PHP**.
 *
 * `Child.firstName` est chiffré au repos (cdc §5.2) : la base ne voit qu'un ciphertext, et un
 * `ORDER BY first_name` trierait des octets aléatoires. Un index aveugle (`BlindIndexHasher`, utilisé
 * pour `Invitation.emailHash`) ne remplace pas ce tri — un HMAC répond à l'égalité, pas à l'ordre —
 * et aucune requête ne cherche un enfant par son prénom. Le tri revient donc à l'application, ce que
 * la volumétrie autorise sans réserve : quelques enfants par famille, tous déjà chargés.
 *
 * L'ordre suit la locale active (`Collator`), donc « Élise » se range avec les E et non après les Z ;
 * ajouter une langue ne demande aucun changement de code. À défaut d'`intl` — que l'image et
 * `INSTALL.md` imposent pourtant — on retombe sur une comparaison sans accents plutôt que sur un
 * ordre d'octets. L'identifiant départage les homonymes : sans cela, deux enfants du même prénom
 * changeraient de place d'un affichage à l'autre, l'ordre de lecture SQL n'étant pas garanti.
 */
final class ChildSorter
{
    /**
     * @param iterable<Child> $children
     *
     * @return list<Child>
     */
    public static function byFirstName(iterable $children): array
    {
        $sorted = \is_array($children) ? array_values($children) : iterator_to_array($children, false);
        $collator = \Collator::create(\Locale::getDefault());

        usort($sorted, static function (Child $a, Child $b) use ($collator): int {
            $comparison = self::compareNames($a->getFirstName(), $b->getFirstName(), $collator);

            return 0 !== $comparison
                ? $comparison
                : strcmp((string) $a->getId(), (string) $b->getId());
        });

        return $sorted;
    }

    private static function compareNames(string $a, string $b, ?\Collator $collator): int
    {
        if (null !== $collator) {
            $comparison = $collator->compare($a, $b);
            if (false !== $comparison) {
                return $comparison;
            }
        }

        return strcmp(self::fold($a), self::fold($b));
    }

    /** Repli sans `intl` : minuscules et accents retirés, pour que « Élise » reste voisine d'« Elise ». */
    private static function fold(string $name): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);

        return mb_strtolower(false === $ascii ? $name : $ascii);
    }
}
