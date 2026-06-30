<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\HolidaySource;
use App\Repository\SchoolHolidayRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Période de vacances scolaires d'une famille (cdc §2.4). Officielle (importée, dates converties
 * UTC→Europe/Paris) ou manuelle. Peut porter une `HolidayRule` (1-1).
 * Unicité naturelle d'upsert : (family, zone, academy, school_year, label, start_date).
 */
#[ORM\Entity(repositoryClass: SchoolHolidayRepository::class)]
#[ORM\Table(name: 'school_holidays')]
#[ORM\UniqueConstraint(name: 'uniq_school_holiday_natural', columns: ['family_id', 'zone', 'academy', 'school_year', 'label', 'start_date'])]
#[ORM\Index(name: 'idx_school_holiday_family_dates', columns: ['family_id', 'start_date', 'end_date'])]
#[ORM\HasLifecycleCallbacks]
class SchoolHoliday
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 160)]
    private string $label;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $zone = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $academy = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $schoolYear = null;

    #[ORM\Column(length: 16, enumType: HolidaySource::class)]
    private HolidaySource $source = HolidaySource::Manual;

    #[ORM\OneToOne(targetEntity: HolidayRule::class, mappedBy: 'schoolHoliday', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?HolidayRule $rule = null;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(?string $zone): static
    {
        $this->zone = $zone;

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

    public function getSchoolYear(): ?string
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(?string $schoolYear): static
    {
        $this->schoolYear = $schoolYear;

        return $this;
    }

    public function getSource(): HolidaySource
    {
        return $this->source;
    }

    public function setSource(HolidaySource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getRule(): ?HolidayRule
    {
        return $this->rule;
    }

    public function setRule(?HolidayRule $rule): static
    {
        $this->rule = $rule;
        $rule?->setSchoolHoliday($this);

        return $this;
    }
}
