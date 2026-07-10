<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\RgpdMode;
use App\Enum\RgpdOperationStatus;
use App\Enum\RgpdOperationType;
use App\Repository\RgpdOperationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Trace applicative des opérations du RGPD Manager (cdc §5.2 / §7.1). Table posée au Lot 0,
 * alimentée au Lot 8 (export, effacement, purge, anonymisation, rapports).
 */
#[ORM\Entity(repositoryClass: RgpdOperationRepository::class)]
#[ORM\Table(name: 'rgpd_operations')]
#[ORM\HasLifecycleCallbacks]
class RgpdOperation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: RgpdOperationType::class)]
    private RgpdOperationType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $subjectUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $requestedBy = null;

    #[ORM\Column(length: 16, enumType: RgpdOperationStatus::class, options: ['default' => 'pending'])]
    private RgpdOperationStatus $status = RgpdOperationStatus::Pending;

    #[ORM\Column(length: 8, enumType: RgpdMode::class, options: ['default' => 'dry_run'])]
    private RgpdMode $mode = RgpdMode::DryRun;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resultRef = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(RgpdOperationType $type)
    {
        $this->id = Uuid::v7();
        $this->type = $type;
        $this->occurredAt = Clock::get()->now();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): RgpdOperationType
    {
        return $this->type;
    }

    public function getSubjectUser(): ?User
    {
        return $this->subjectUser;
    }

    public function setSubjectUser(?User $user): static
    {
        $this->subjectUser = $user;

        return $this;
    }

    public function getRequestedBy(): ?User
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?User $user): static
    {
        $this->requestedBy = $user;

        return $this;
    }

    public function getStatus(): RgpdOperationStatus
    {
        return $this->status;
    }

    public function setStatus(RgpdOperationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMode(): RgpdMode
    {
        return $this->mode;
    }

    public function setMode(RgpdMode $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getResultRef(): ?string
    {
        return $this->resultRef;
    }

    public function setResultRef(?string $ref): static
    {
        $this->resultRef = $ref;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
