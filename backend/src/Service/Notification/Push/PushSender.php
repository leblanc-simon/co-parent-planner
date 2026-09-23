<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\DeviceToken;

/**
 * Passerelle push (Lot 11 §0.2) : isole la communication avec FCM derrière une interface, pour que
 * la CI n'appelle jamais le réseau (un fake la remplace en `test`).
 */
interface PushSender
{
    /**
     * Faux tant que les identifiants de la passerelle ne sont pas fournis : le canal push est alors
     * éteint, et le pipeline n'enfile même pas de message.
     */
    public function isEnabled(): bool;

    /** Envoie à **un** appareil. Ne lève pas : toute issue est traduite en {@see PushResult}. */
    public function send(DeviceToken $device, PushContent $content): PushResult;
}
