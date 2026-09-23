<?php

declare(strict_types=1);

namespace App\Exception;

/** Appareil inexistant **ou** appartenant à un autre compte (Lot 11 US-PUSH-3) — non énumérant. */
final class DeviceNotFoundException extends AbstractProblemException
{
    public const string TYPE = '/errors/device-not-found';

    public function __construct()
    {
        parent::__construct(404, self::TYPE, 'device.error.not_found');
    }
}
