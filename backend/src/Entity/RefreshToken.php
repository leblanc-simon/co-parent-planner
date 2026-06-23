<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Refresh token rotatif (Lot 0 §3.3, §5.1). Stocké **haché**. La rotation révoque l'ancien ;
 * la réutilisation d'un token révoqué déclenche l'invalidation de toute la `tokenFamilyId`.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_family', columns: ['token_family_id'])]
#[ORM\HasLifecycleCallbacks]
class RefreshToken
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** SHA-256 du token clair (jamais le clair en base). */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $tokenFamilyId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** Hash du token qui a remplacé celui-ci lors de la rotation (traçabilité). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $replacedBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->tokenFamilyId = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getTokenFamilyId(): Uuid
    {
        return $this->tokenFamilyId;
    }

    public function setTokenFamilyId(Uuid $id): static
    {
        $this->tokenFamilyId = $id;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?string $replacedBy = null): static
    {
        $this->revokedAt ??= Clock::get()->now();
        $this->replacedBy = $replacedBy;

        return $this;
    }

    public function getReplacedBy(): ?string
    {
        return $this->replacedBy;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= Clock::get()->now();
    }

    public function isUsable(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }
}
