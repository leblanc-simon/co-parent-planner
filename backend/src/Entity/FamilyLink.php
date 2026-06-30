<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\FamilyLinkStatus;
use App\Repository\FamilyLinkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Lien bidirectionnel logique entre deux familles (cdc §2.6, Lot 1 §3.6).
 *
 * Stocké **une seule fois** mais interprété dans les deux sens. L'unicité indépendante du sens
 * est garantie par la paire canonique `(pair_low, pair_high)` = (min, max) des deux UUID,
 * portée par un index unique. **Aucun overlay** ici : l'exploitation RO de l'agenda est Lot 5.
 */
#[ORM\Entity(repositoryClass: FamilyLinkRepository::class)]
#[ORM\Table(name: 'family_links')]
#[ORM\UniqueConstraint(name: 'uniq_family_link_pair', columns: ['pair_low', 'pair_high'])]
#[ORM\HasLifecycleCallbacks]
class FamilyLink
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(name: 'linked_family_id', nullable: false, onDelete: 'CASCADE')]
    private Family $linkedFamily;

    #[ORM\Column(length: 16, enumType: FamilyLinkStatus::class, options: ['default' => 'pending'])]
    private FamilyLinkStatus $status = FamilyLinkStatus::Pending;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** Paire canonique normalisée (min des deux UUID) — porte l'unicité bidirectionnelle. */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $pairLow;

    /** Paire canonique normalisée (max des deux UUID). */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $pairHigh;

    public function __construct(Family $family, Family $linkedFamily, ?User $createdBy = null)
    {
        $this->id = Uuid::v7();
        $this->family = $family;
        $this->linkedFamily = $linkedFamily;
        $this->createdBy = $createdBy;
        [$this->pairLow, $this->pairHigh] = self::canonicalPair($family->getId(), $linkedFamily->getId());
    }

    /**
     * Paire canonique (ordre lexicographique des UUID) — clé d'unicité indépendante du sens (§3.6).
     *
     * @return array{0: Uuid, 1: Uuid}
     */
    public static function canonicalPair(Uuid $a, Uuid $b): array
    {
        return strcmp((string) $a, (string) $b) <= 0 ? [$a, $b] : [$b, $a];
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }

    public function getLinkedFamily(): Family
    {
        return $this->linkedFamily;
    }

    public function getStatus(): FamilyLinkStatus
    {
        return $this->status;
    }

    public function setStatus(FamilyLinkStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getPairLow(): Uuid
    {
        return $this->pairLow;
    }

    public function getPairHigh(): Uuid
    {
        return $this->pairHigh;
    }

    /** « L'autre » famille du lien, du point de vue de la famille fournie. */
    public function otherFamily(Family $from): Family
    {
        return $this->family->getId()->equals($from->getId()) ? $this->linkedFamily : $this->family;
    }
}
