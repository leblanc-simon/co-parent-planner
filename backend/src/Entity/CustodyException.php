<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Repository\CustodyExceptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Exception manuelle de garde (échange d'un jour ou d'une plage — cdc §2.4). Priorité maximale :
 * écrase base ET vacances sur sa plage. `reason` = texte court libre **non sensible**.
 */
#[ORM\Entity(repositoryClass: CustodyExceptionRepository::class)]
#[ORM\Table(name: 'custody_exceptions')]
#[ORM\Index(name: 'idx_custody_exception_family', columns: ['family_id', 'start_date', 'end_date'])]
#[ORM\HasLifecycleCallbacks]
class CustodyException
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Membership $parentMembership = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $createdBy = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function setFamily(Family $family): static
    {
        $this->family = $family;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getParentMembership(): ?Membership
    {
        return $this->parentMembership;
    }

    public function setParentMembership(?Membership $parentMembership): static
    {
        $this->parentMembership = $parentMembership;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedBy(): ?Membership
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Membership $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
