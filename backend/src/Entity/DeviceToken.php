<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\DevicePlatform;
use App\Enum\DeviceTokenStatus;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton de notification push d'un appareil (Lot 11 §5.2), rattaché à un **utilisateur** et non à
 * une famille : un compte membre de deux familles reçoit les push des deux sur le même appareil.
 *
 * Le jeton est chiffré au repos 🔒 — il suffit à pousser du contenu vers l'appareil. L'unicité et
 * la recherche passent par `tokenHash` (HMAC keyé), le ciphertext GCM n'étant pas comparable par
 * égalité. L'index unique partiel garantit **au plus un enregistrement actif par valeur de jeton** :
 * un appareil passé d'un compte à un autre ne peut pas recevoir les push des deux.
 */
#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\Table(name: 'device_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_device_token_active', columns: ['token_hash'], options: ['where' => "((status)::text = 'active'::text)"])]
#[ORM\Index(name: 'idx_device_token_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_device_token_installation', columns: ['user_id', 'installation_id'])]
#[ORM\HasLifecycleCallbacks]
class DeviceToken
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: DevicePlatform::class)]
    private DevicePlatform $platform;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Acheminer les notifications push vers l\'appareil de l\'utilisateur.',
        legalBasis: 'Consentement (permission système accordée + préférence push activée).',
        retention: 'Tant que l\'appareil est actif ; révoqués/invalides purgés après une rétention courte (Lot 11).',
        recipients: 'Passerelle Firebase Cloud Messaging (Google), relais APNs (Apple) pour iOS.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $token;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Index aveugle (HMAC) du jeton — unicité et recherche sans déchiffrer.',
        legalBasis: 'Intérêt légitime (intégrité du routage des notifications).',
        retention: 'Identique au jeton.',
        recipients: 'Interne (jamais exposé).',
        encrypted: false,
    )]
    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Reconnaître une même installation de l\'app pour détecter la rotation du jeton.',
        legalBasis: 'Intérêt légitime (un seul jeton actif par installation).',
        retention: 'Identique au jeton.',
        recipients: 'Interne (jamais exposé).',
        encrypted: false,
    )]
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $installationId = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $appVersion = null;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Permettre à l\'utilisateur de reconnaître ses appareils avant d\'en révoquer un.',
        legalBasis: 'Intérêt légitime (gestion des appareils par l\'utilisateur).',
        retention: 'Identique au jeton.',
        recipients: 'L\'utilisateur lui-même.',
        encrypted: false,
    )]
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deviceModel = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(length: 16, enumType: DeviceTokenStatus::class, options: ['default' => 'active'])]
    private DeviceTokenStatus $status = DeviceTokenStatus::Active;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invalidatedAt = null;

    public function __construct(User $user, DevicePlatform $platform, string $token, string $tokenHash)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->platform = $platform;
        $this->token = $token;
        $this->tokenHash = $tokenHash;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPlatform(): DevicePlatform
    {
        return $this->platform;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getInstallationId(): ?string
    {
        return $this->installationId;
    }

    public function setInstallationId(?string $installationId): static
    {
        $this->installationId = $installationId;

        return $this;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): static
    {
        $this->appVersion = $appVersion;

        return $this;
    }

    public function getDeviceModel(): ?string
    {
        return $this->deviceModel;
    }

    public function setDeviceModel(?string $deviceModel): static
    {
        $this->deviceModel = $deviceModel;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getStatus(): DeviceTokenStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return DeviceTokenStatus::Active === $this->status;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function seen(\DateTimeImmutable $at): static
    {
        $this->lastSeenAt = $at;

        return $this;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function delivered(\DateTimeImmutable $at): static
    {
        $this->lastSuccessAt = $at;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $at): static
    {
        if ($this->isActive()) {
            $this->status = DeviceTokenStatus::Revoked;
            $this->revokedAt = $at;
        }

        return $this;
    }

    public function getInvalidatedAt(): ?\DateTimeImmutable
    {
        return $this->invalidatedAt;
    }

    /** Refus définitif de la passerelle : le jeton ne sera plus jamais routé. */
    public function invalidate(\DateTimeImmutable $at): static
    {
        if ($this->isActive()) {
            $this->status = DeviceTokenStatus::Invalid;
            $this->invalidatedAt = $at;
        }

        return $this;
    }
}
