<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

/**
 * Échec passager d'au moins un envoi push : levée en fin de traitement pour que Messenger rejoue le
 * message (nouvel essai borné par la stratégie du transport `async`, puis file `failed`). Les
 * appareils déjà servis ne sont pas re-servis au rejeu — leur clé d'idempotence est posée.
 */
final class PushDeliveryException extends \RuntimeException
{
}
