<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\DeviceToken;
use App\Service\Notification\Push\PushContent;
use App\Service\Notification\Push\PushResult;
use App\Service\Notification\Push\PushSender;

/**
 * Passerelle push de test (Lot 11 DoD) : aucun appel réseau. Enregistre chaque envoi et rend,
 * jeton par jeton, l'issue scriptée par le test (`Delivered` par défaut).
 */
final class FakePushSender implements PushSender
{
    public bool $enabled = true;

    /** @var list<array{token: string, platform: string, content: PushContent}> */
    public array $sent = [];

    /** @var array<string, PushResult> issue forcée par valeur de jeton */
    private array $results = [];

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(DeviceToken $device, PushContent $content): PushResult
    {
        $result = $this->results[$device->getToken()] ?? PushResult::Delivered;
        if (PushResult::Delivered === $result) {
            $this->sent[] = ['token' => $device->getToken(), 'platform' => $device->getPlatform()->value, 'content' => $content];
        }

        return $result;
    }

    public function willAnswer(string $token, PushResult $result): void
    {
        $this->results[$token] = $result;
    }

    /** @return list<string> jetons effectivement servis */
    public function sentTokens(): array
    {
        return array_map(static fn (array $s): string => $s['token'], $this->sent);
    }
}
