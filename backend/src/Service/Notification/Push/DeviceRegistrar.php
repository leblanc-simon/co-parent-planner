<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Dto\Device\RegisterDeviceRequest;
use App\Entity\DeviceToken;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\DevicePlatform;
use App\Repository\DeviceTokenRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Encryption\BlindIndexHasher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Cycle de vie des jetons d'appareil (Lot 11 §3.1–3.2).
 *
 * Trois invariants, tenus sous un verrou consultatif sur l'empreinte du jeton — deux requêtes du
 * même appareil au démarrage ne créent jamais deux lignes :
 *  1. **au plus un enregistrement actif par valeur de jeton** (index unique partiel) ;
 *  2. **un jeton ne sert qu'un compte** : réenregistré par un autre utilisateur (téléphone passé
 *     de main en main, changement de compte), l'ancien enregistrement est révoqué — un push n'arrive
 *     jamais sur le mauvais compte ;
 *  3. **un seul jeton actif par installation** : quand l'OS fait tourner le jeton, l'ancien est
 *     révoqué dès que l'app présente le nouveau avec le même `installationId`.
 *
 * Le journal d'audit ne reçoit que l'identifiant et la plateforme, jamais le jeton.
 */
final class DeviceRegistrar
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviceTokenRepository $devices,
        private readonly BlindIndexHasher $blindIndex,
        private readonly AuditLogger $audit,
        private readonly ClockInterface $clock,
    ) {
    }

    public function register(User $user, RegisterDeviceRequest $request, ?string $ip): DeviceToken
    {
        $platform = DevicePlatform::from((string) $request->platform);
        $token = trim((string) $request->token);
        $hash = $this->blindIndex->hashExact($token);
        $installationId = self::blankToNull($request->installationId);

        return $this->em->wrapInTransaction(function () use ($user, $platform, $token, $hash, $installationId, $request, $ip): DeviceToken {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                ['k' => 'device:'.$hash],
            );
            $now = $this->clock->now();

            $existing = $this->devices->findActiveByTokenHash($hash);
            if (null !== $existing && $existing->getUser()->getId()->equals($user->getId())) {
                $this->describe($existing, $request, $installationId)->seen($now);

                return $existing;
            }
            if (null !== $existing) {
                $existing->revoke($now);
                $this->audit->persist(
                    AuditAction::DeviceReassigned,
                    $user,
                    ip: $ip,
                    metadata: ['platform' => $existing->getPlatform()->value, 'previous_owner' => (string) $existing->getUser()->getId()],
                    entityType: 'DeviceToken',
                    entityId: (string) $existing->getId(),
                );
            }

            if (null !== $installationId) {
                // Rotation : l'installation présentait un autre jeton, qui ne sera plus jamais servi.
                $this->devices->findActiveByInstallation($user, $installationId)?->revoke($now);
            }
            // Les révocations doivent atteindre la base avant l'insertion : l'index unique partiel
            // ne tolère pas deux lignes actives pour la même empreinte, même le temps d'un flush.
            $this->em->flush();

            $device = $this->describe(new DeviceToken($user, $platform, $token, $hash), $request, $installationId)->seen($now);
            $this->em->persist($device);
            $this->audit->persist(
                AuditAction::DeviceRegistered,
                $user,
                ip: $ip,
                metadata: ['platform' => $platform->value],
                entityType: 'DeviceToken',
                entityId: (string) $device->getId(),
            );

            return $device;
        });
    }

    public function revoke(DeviceToken $device, User $actor, ?string $ip): void
    {
        $device->revoke($this->clock->now());
        $this->audit->log(
            AuditAction::DeviceRevoked,
            $actor,
            ip: $ip,
            metadata: ['platform' => $device->getPlatform()->value],
            entityType: 'DeviceToken',
            entityId: (string) $device->getId(),
        );
    }

    /** Champs descriptifs : une valeur absente de la requête ne remplace pas celle déjà connue. */
    private function describe(DeviceToken $device, RegisterDeviceRequest $request, ?string $installationId): DeviceToken
    {
        if (null !== $installationId) {
            $device->setInstallationId($installationId);
        }
        if (null !== ($appVersion = self::blankToNull($request->appVersion))) {
            $device->setAppVersion($appVersion);
        }
        if (null !== ($model = self::blankToNull($request->deviceModel))) {
            $device->setDeviceModel($model);
        }
        if (null !== ($locale = self::blankToNull($request->locale))) {
            $device->setLocale($locale);
        }

        return $device;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return null === $value || '' === $value ? null : $value;
    }
}
