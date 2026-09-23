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
 * est implicitement scopé à l'utilisateur du token (jamais un autre compte). Deux canaux, réglés
 * indépendamment par catégorie : `email` (opt-out) et `push` (opt-in, Lot 11 US-PUSH-4).
 * Quiet-hours `HH:MM` interprétées dans le fuseau de la famille active, communes aux deux canaux.
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
     * Activation e-mail par catégorie (`custody`, `agenda`, `expenses`, `exchanges`, `invitation`).
     * Absence d'enregistrement ⇒ activé.
     *
     * @var array<string, bool>
     */
    public array $email = [];

    /**
     * Activation push (app mobile) par catégorie, mêmes clés que `email`. Absence d'enregistrement
     * ⇒ **désactivé** : rien n'est poussé sans un choix explicite. Sans effet tant qu'aucun appareil
     * n'est enregistré (`/api/me/devices`), c'est-à-dire tant que la permission système manque.
     *
     * @var array<string, bool>
     */
    public array $push = [];

    public ?string $quietHoursStart = null;
    public ?string $quietHoursEnd = null;
    public string $locale = 'fr-FR';
}
