<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\OneTimeTokenPurpose;
use App\Repository\OneTimeTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Jeton à usage unique, typé par finalité (vérification e-mail / reset mot de passe) — Lot 0 §5.1.
 * Stocké **haché**, expirant, consommable une seule fois.
 */
#[ORM\Entity(repositoryClass: OneTimeTokenRepository::class)]
#[ORM\Table(name: 'one_time_tokens')]
#[ORM\Index(name: 'idx_ott_hash', columns: ['token_hash'])]
#[ORM\HasLifecycleCallbacks]
class OneTimeToken
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32, enumType: OneTimeTokenPurpose::class)]
    private OneTimeTokenPurpose $purpose;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getPurpose(): OneTimeTokenPurpose
    {
        return $this->purpose;
    }

    public function setPurpose(OneTimeTokenPurpose $purpose): static
    {
        $this->purpose = $purpose;

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

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getConsumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function consume(): static
    {
        $this->consumedAt ??= Clock::get()->now();

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= Clock::get()->now();
    }

    public function isConsumed(): bool
    {
        return null !== $this->consumedAt;
    }

    public function isUsable(): bool
    {
        return !$this->isConsumed() && !$this->isExpired();
    }
}
