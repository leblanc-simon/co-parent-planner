<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\CustodyDaySource;
use App\Repository\CustodyDayRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Jour de garde matérialisé (cdc §2.4) : un par (famille, date). Idempotence garantie par
 * l'unicité `(family_id, date)` + upsert. `source` porte la priorité base < holiday < exception.
 */
#[ORM\Entity(repositoryClass: CustodyDayRepository::class)]
#[ORM\Table(name: 'custody_days')]
#[ORM\UniqueConstraint(name: 'uniq_custody_day', columns: ['family_id', 'date'])]
#[ORM\Index(name: 'idx_custody_day_family_date', columns: ['family_id', 'date'])]
#[ORM\HasLifecycleCallbacks]
class CustodyDay
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Parent attribué. Nullable (`SET NULL`) pour survivre à l'anonymisation d'un membre (§4.5). */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Membership $parentMembership = null;

    #[ORM\Column(length: 16, enumType: CustodyDaySource::class)]
    private CustodyDaySource $source = CustodyDaySource::Base;

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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

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

    public function getSource(): CustodyDaySource
    {
        return $this->source;
    }

    public function setSource(CustodyDaySource $source): static
    {
        $this->source = $source;

        return $this;
    }
}
