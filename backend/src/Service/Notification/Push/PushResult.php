<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

/**
 * Issue d'un envoi vers la passerelle (Lot 11 §3.5). Seul `InvalidToken` retire le jeton : une
 * erreur passagère qui l'invaliderait rendrait l'appareil injoignable pour de bon.
 */
enum PushResult
{
    /** Accepté par la passerelle. */
    case Delivered;
    /** Refus définitif du jeton (désinstallation, jeton d'un autre projet…) → jeton invalidé. */
    case InvalidToken;
    /** Indisponibilité, quota, délai dépassé → nouvel essai (Messenger), jeton conservé. */
    case Retryable;
    /** Identifiants serveur refusés (compte de service, clé APNs côté Firebase) → alerte + nouvel essai. */
    case AuthFailure;
    /** Message refusé pour une raison qui ne tient ni au jeton ni au réseau → abandon, journalisé. */
    case Rejected;
}
