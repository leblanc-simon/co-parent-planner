<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Plateforme d'un appareil enregistré pour le push (Lot 11 §5.2).
 *
 * Les deux plateformes passent par FCM (arbitrage du 2026-09-23 : FCM relaie vers APNs) ; la
 * plateforme reste portée parce qu'elle façonne le message — bloc `apns` d'un côté, `android` de
 * l'autre — et parce que c'est le premier indice d'un diagnostic de livraison.
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
