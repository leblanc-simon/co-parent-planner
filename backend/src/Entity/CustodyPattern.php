<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\CustodyPatternKind;
use App\Repository\CustodyPatternRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Modèle de rythme de garde d'une famille (cdc §2.4, Lot 3 §5.1). Un seul par famille (1-1).
 * Le moteur en dérive les `CustodyDay` de `source=base`.
 */
#[ORM\Entity(repositoryClass: CustodyPatternRepository::class)]
#[ORM\Table(name: 'custody_patterns')]
#[ORM\UniqueConstraint(name: 'uniq_custody_pattern_family', columns: ['family_id'])]
#[ORM\HasLifecycleCallbacks]
class CustodyPattern
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 32, enumType: CustodyPatternKind::class)]
    private CustodyPatternKind $kind;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $anchorDate;

    /** Jour d'échange : 0=dimanche … 6=samedi (convention figée §4.5). */
    #[ORM\Column(type: 'smallint')]
    private int $handoverWeekday = 0;

    /**
     * Heure d'échange locale (HH:MM) : instant où les enfants passent d'un parent à l'autre, le
     * **dernier** jour d'un bloc de garde (cf. `DaySegments`). Le moteur en découpe les journées.
     */
    #[ORM\Column(length: 5)]
    private string $handoverTime = '19:00';

    /**
     * Heure à laquelle « la journée commence » (HH:MM), proposée par défaut quand un échange
     * ponctuel ne peut pas s'adosser à la transmission de la veille — la garde reprend alors le
     * matin même plutôt qu'à minuit.
     */
    #[ORM\Column(length: 5, options: ['default' => '08:00'])]
    private string $dayStartTime = '08:00';

    /** @var list<string>|null 14 créneaux "A"/"B" — requis ssi kind=custom. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $customPattern = null;

    /** @var array<string, mixed>|null Config midweek_weekends — requis ssi kind=midweek_weekends. */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $settings = null;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'parent_a_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $parentA = null;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'parent_b_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $parentB = null;

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

    public function getKind(): CustodyPatternKind
    {
        return $this->kind;
    }

    public function setKind(CustodyPatternKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getAnchorDate(): \DateTimeImmutable
    {
        return $this->anchorDate;
    }

    public function setAnchorDate(\DateTimeImmutable $anchorDate): static
    {
        $this->anchorDate = $anchorDate;

        return $this;
    }

    public function getHandoverWeekday(): int
    {
        return $this->handoverWeekday;
    }

    public function setHandoverWeekday(int $handoverWeekday): static
    {
        $this->handoverWeekday = $handoverWeekday;

        return $this;
    }

    public function getHandoverTime(): string
    {
        return $this->handoverTime;
    }

    public function setHandoverTime(string $handoverTime): static
    {
        $this->handoverTime = $handoverTime;

        return $this;
    }

    public function getDayStartTime(): string
    {
        return $this->dayStartTime;
    }

    public function setDayStartTime(string $dayStartTime): static
    {
        $this->dayStartTime = $dayStartTime;

        return $this;
    }

    /** @return list<string>|null */
    public function getCustomPattern(): ?array
    {
        return $this->customPattern;
    }

    /** @param list<string>|null $customPattern */
    public function setCustomPattern(?array $customPattern): static
    {
        $this->customPattern = $customPattern;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getSettings(): ?array
    {
        return $this->settings;
    }

    /** @param array<string, mixed>|null $settings */
    public function setSettings(?array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function getParentA(): ?Membership
    {
        return $this->parentA;
    }

    public function setParentA(?Membership $parentA): static
    {
        $this->parentA = $parentA;

        return $this;
    }

    public function getParentB(): ?Membership
    {
        return $this->parentB;
    }

    public function setParentB(?Membership $parentB): static
    {
        $this->parentB = $parentB;

        return $this;
    }
}
