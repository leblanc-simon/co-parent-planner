<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\DeviceToken;
use App\Entity\Family;
use App\Entity\User;

/**
 * Résolution de la locale d'un destinataire au moment du rendu (Lot 7 §3.5) : locale utilisateur, puis
 * locale de l'appareil pour un push (Lot 11 §3.4 — un push n'a pas d'`Accept-Language`), puis locale
 * de la famille, puis `fr-FR` par défaut. Jamais d'échec faute de locale.
 */
final class LocaleResolver
{
    public function resolve(User $user, ?Family $family, ?DeviceToken $device = null): string
    {
        if ('' !== $user->getLocale()) {
            return $user->getLocale();
        }
        $deviceLocale = $device?->getLocale();
        if (null !== $deviceLocale && '' !== $deviceLocale) {
            return $deviceLocale;
        }
        $familyLocale = $family?->getSettings()['locale'] ?? null;
        if (\is_string($familyLocale) && '' !== $familyLocale) {
            return $familyLocale;
        }

        return 'fr-FR';
    }
}
