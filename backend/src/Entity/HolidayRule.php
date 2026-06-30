<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\HolidayRuleKind;
use App\Repository\HolidayRuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Règle de garde appliquée à une période de vacances (cdc §2.4, Lot 3 §3.3). 1-1 avec SchoolHoliday.
 * `params` est un jsonb dont le schéma dépend de `kind` (validé à l'écriture).
 */
#[ORM\Entity(repositoryClass: HolidayRuleRepository::class)]
#[ORM\Table(name: 'holiday_rules')]
#[ORM\UniqueConstraint(name: 'uniq_holiday_rule_holiday', columns: ['school_holiday_id'])]
#[ORM\HasLifecycleCallbacks]
class HolidayRule
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: SchoolHoliday::class, inversedBy: 'rule')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SchoolHoliday $schoolHoliday;

    #[ORM\Column(length: 32, enumType: HolidayRuleKind::class)]
    private HolidayRuleKind $kind;

    /** @var array<string, mixed> schéma dépendant de `kind` (cf. §5.1) */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $params = [];

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSchoolHoliday(): SchoolHoliday
    {
        return $this->schoolHoliday;
    }

    public function setSchoolHoliday(SchoolHoliday $schoolHoliday): static
    {
        $this->schoolHoliday = $schoolHoliday;

        return $this;
    }

    public function getKind(): HolidayRuleKind
    {
        return $this->kind;
    }

    public function setKind(HolidayRuleKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getParams(): array
    {
        return $this->params;
    }

    /** @param array<string, mixed> $params */
    public function setParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }
}
