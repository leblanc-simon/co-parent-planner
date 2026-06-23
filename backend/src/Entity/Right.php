<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Droit granulaire d'un membre sur un domaine (cdc §2.2). Pour un parent, ignoré/forcé `edit`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rights')]
#[ORM\UniqueConstraint(name: 'uniq_right_membership_domain', columns: ['membership_id', 'domain'])]
#[ORM\HasLifecycleCallbacks]
class Right
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Membership::class, inversedBy: 'rights')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

    #[ORM\Column(length: 32, enumType: RightDomain::class)]
    private RightDomain $domain;

    #[ORM\Column(length: 8, enumType: RightLevel::class)]
    private RightLevel $level;

    public function __construct(?RightDomain $domain = null, ?RightLevel $level = null)
    {
        $this->id = Uuid::v7();
        if (null !== $domain) {
            $this->domain = $domain;
        }
        if (null !== $level) {
            $this->level = $level;
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMembership(): Membership
    {
        return $this->membership;
    }

    public function setMembership(Membership $membership): static
    {
        $this->membership = $membership;

        return $this;
    }

    public function getDomain(): RightDomain
    {
        return $this->domain;
    }

    public function setDomain(RightDomain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getLevel(): RightLevel
    {
        return $this->level;
    }

    public function setLevel(RightLevel $level): static
    {
        $this->level = $level;

        return $this;
    }
}
