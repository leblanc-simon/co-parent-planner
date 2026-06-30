<?php

declare(strict_types=1);

namespace App\Service\Child;

use App\Exception\ChildInputException;

/**
 * Normalise et valide la liste d'informations pratiques d'un enfant (Lot 2 §3.2).
 *
 * Paires libres {label, value} — **aucun preset médical** (invariant). L'ordre du tableau est
 * l'ordre d'affichage : aucun tri implicite. Lignes entièrement vides ignorées ; valeur sans
 * libellé refusée (422) ; libellé sans valeur toléré.
 */
final class InfoNormalizer
{
    private const int MAX_ITEMS = 50;
    private const int MAX_LABEL = 120;
    private const int MAX_VALUE = 500;

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return list<array{label: string, value: string}>
     */
    public function normalize(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));

            if ('' === $label && '' === $value) {
                continue; // ligne entièrement vide → ignorée
            }
            if ('' === $label) {
                throw new ChildInputException('child.error.info_label_required');
            }
            if (mb_strlen($label) > self::MAX_LABEL || mb_strlen($value) > self::MAX_VALUE) {
                throw new ChildInputException('child.error.info_too_long');
            }
            $result[] = ['label' => $label, 'value' => $value];
        }

        if (\count($result) > self::MAX_ITEMS) {
            throw new ChildInputException('child.error.info_too_many');
        }

        return $result;
    }
}
