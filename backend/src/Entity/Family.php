<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\FamilyType;
use App\Repository\FamilyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Famille (cdc §2.1). Squelette Lot 0 : réglages détaillés au Lot 1.
 */
#[ORM\Entity(repositoryClass: FamilyRepository::class)]
#[ORM\Table(name: 'families')]
#[ORM\HasLifecycleCallbacks]
class Family
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 16, enumType: FamilyType::class)]
    private FamilyType $type;

    #[ORM\Column(length: 64, options: ['default' => 'Europe/Paris'])]
    private string $timezone = 'Europe/Paris';

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $schoolZone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $academy = null;

    /** Module Dépenses (v2) — inerte en v1 (cdc §1.3). */
    #[ORM\Column(options: ['default' => false])]
    private bool $expensesEnabled = false;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $settings = [];

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'family')]
    private Collection $memberships;

    /** @var Collection<int, Invitation> */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'family')]
    private Collection $invitations;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->memberships = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): FamilyType
    {
        return $this->type;
    }

    public function setType(FamilyType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getSchoolZone(): ?string
    {
        return $this->schoolZone;
    }

    public function setSchoolZone(?string $schoolZone): static
    {
        $this->schoolZone = $schoolZone;

        return $this;
    }

    public function getAcademy(): ?string
    {
        return $this->academy;
    }

    public function setAcademy(?string $academy): static
    {
        $this->academy = $academy;

        return $this;
    }

    public function isExpensesEnabled(): bool
    {
        return $this->expensesEnabled;
    }

    public function setExpensesEnabled(bool $enabled): static
    {
        $this->expensesEnabled = $enabled;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /** @param array<string, mixed> $settings */
    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function hasCustody(): bool
    {
        return $this->type->hasCustody();
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /** @return Collection<int, Invitation> */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }
}
