<?php

declare(strict_types=1);

namespace App\Dto\Device;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Enregistrement ou rafraîchissement d'un appareil (Lot 11 US-PUSH-1/2). Rejouable : le même jeton
 * renvoyé met à jour l'enregistrement existant au lieu d'en créer un second.
 */
final class RegisterDeviceRequest
{
    #[Assert\NotBlank(message: 'device.error.platform_invalid')]
    #[Assert\Choice(choices: ['ios', 'android'], message: 'device.error.platform_invalid')]
    public ?string $platform = null;

    /** Jeton FCM (quelques centaines de caractères) ; au-delà de 4 Kio ce n'est pas un jeton. */
    #[Assert\NotBlank(message: 'device.error.token_invalid')]
    #[Assert\Length(max: 4096, maxMessage: 'device.error.token_invalid')]
    public ?string $token = null;

    /** Identifiant stable de l'installation de l'app : détecte la rotation du jeton (US-PUSH-2). */
    #[Assert\Length(max: 128)]
    public ?string $installationId = null;

    #[Assert\Length(max: 32)]
    public ?string $appVersion = null;

    #[Assert\Length(max: 64)]
    public ?string $deviceModel = null;

    #[Assert\Length(max: 16)]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2,3}(-[a-zA-Z]{4})?(-([a-zA-Z]{2}|[0-9]{3}))?$/', message: 'device.error.locale_invalid')]
    public ?string $locale = null;
}
