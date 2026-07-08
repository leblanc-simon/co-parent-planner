<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Family;
use App\Entity\User;

/**
 * Résolution de la locale d'un destinataire au moment du rendu (Lot 7 §3.5) : locale utilisateur, puis
 * locale de la famille, puis `fr-FR` par défaut. Jamais d'échec faute de locale.
 */
final class LocaleResolver
{
    public function resolve(User $user, ?Family $family): string
    {
        if ('' !== $user->getLocale()) {
            return $user->getLocale();
        }
        $familyLocale = $family?->getSettings()['locale'] ?? null;
        if (\is_string($familyLocale) && '' !== $familyLocale) {
            return $familyLocale;
        }

        return 'fr-FR';
    }
}
