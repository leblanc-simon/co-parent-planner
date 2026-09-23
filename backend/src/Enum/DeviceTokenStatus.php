<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cycle de vie d'un jeton d'appareil (Lot 11 §5.2).
 *
 * - `active` : éligible aux envois ;
 * - `revoked` : retiré par l'utilisateur (logout, appareil perdu), réassigné à un autre compte ou
 *   remplacé par une rotation sur la même installation ;
 * - `invalid` : refusé définitivement par la passerelle (`UNREGISTERED`…).
 *
 * Seul `active` est routé ; les deux autres attendent la purge RGPD.
 */
enum DeviceTokenStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Invalid = 'invalid';
}
