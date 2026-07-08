<?php

declare(strict_types=1);

namespace App\Dto\Notification;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use App\State\Notification\NotificationPrefsProcessor;
use App\State\Notification\NotificationPrefsProvider;

/**
 * US-SET-1 (Lot 7) — préférences de notification du compte courant. `/api/me/notification-prefs`
 * est implicitement scopé à l'utilisateur du token (jamais un autre compte). Canal `email` seul en
 * v1 ; catégorie `expenses` et canal `push` masqués (réservés v2). Quiet-hours `HH:MM` interprétées
 * dans le fuseau de la famille active.
 */
#[ApiResource(
    shortName: 'NotificationPrefs',
    operations: [
        new Get(uriTemplate: '/me/notification-prefs', provider: NotificationPrefsProvider::class),
        new Put(uriTemplate: '/me/notification-prefs', provider: NotificationPrefsProvider::class, processor: NotificationPrefsProcessor::class),
    ],
)]
class NotificationPrefsResource
{
    /**
     * Activation e-mail par catégorie v1 (`custody`, `agenda`, `exchanges`, `invitation`).
     *
     * @var array<string, bool>
     */
    public array $email = [];

    public ?string $quietHoursStart = null;
    public ?string $quietHoursEnd = null;
    public string $locale = 'fr-FR';
}
