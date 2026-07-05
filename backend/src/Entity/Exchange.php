<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\ExchangeStatus;
use App\Enum\ExchangeType;
use App\Repository\ExchangeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Échange de coordination co-parentale (cdc §2.6, Lot 6) : une **discussion** (fil ouvert) ou une
 * **décision** (≥ 2 options à trancher). Scopé famille. L'auteur est un `Membership` en
 * `ON DELETE SET NULL` (l'échange survit à l'anonymisation d'un membre, §4.8). Rattaché à un enfant
 * (`child_id`) **ou** à toute la famille (`child_id = null`). L'échéance (`deadline`) est une **date** ;
 * le marqueur « en retard » est **dérivé** (jamais matérialisé, fuseau famille §3.4).
 */
#[ORM\Entity(repositoryClass: ExchangeRepository::class)]
#[ORM\Table(name: 'exchanges')]
#[ORM\Index(name: 'idx_exchange_family_status', columns: ['family_id', 'status'])]
// Idempotence de création (Idempotency-Key outbox mobile §4.2) : au plus un échange par (famille, clé).
#[ORM\UniqueConstraint(name: 'uniq_exchange_idempotency', columns: ['family_id', 'idempotency_key'], options: ['where' => '(idempotency_key IS NOT NULL)'])]
#[ORM\HasLifecycleCallbacks]
class Exchange
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 16, enumType: ExchangeType::class)]
    private ExchangeType $type = ExchangeType::Discussion;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    /** Auteur ; **nullable + SET NULL** pour survivre à l'anonymisation d'un membre (§4.8). */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'author_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $authorMembership = null;

    /** Rattachement à un enfant **ou** « toute la famille » si `null` (§5.1). */
    #[ORM\ManyToOne(targetEntity: Child::class)]
    #[ORM\JoinColumn(name: 'child_id', nullable: true, onDelete: 'SET NULL')]
    private ?Child $child = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(length: 16, enumType: ExchangeStatus::class, options: ['default' => 'open'])]
    private ExchangeStatus $status = ExchangeStatus::Open;

    /** Option retenue — renseignée uniquement pour une **décision tranchée** ; sinon `null` (§5.2). */
    #[ORM\ManyToOne(targetEntity: ExchangeOption::class)]
    #[ORM\JoinColumn(name: 'decided_option_id', nullable: true, onDelete: 'SET NULL')]
    private ?ExchangeOption $decidedOption = null;

    /** Clé d'idempotence de création (Idempotency-Key), scoped famille (§4.2). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $idempotencyKey = null;

    /** Verrou optimiste : protège la clôture/forçage concurrent (§4.2). */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

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

    public function getType(): ExchangeType
    {
        return $this->type;
    }

    public function setType(ExchangeType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getAuthorMembership(): ?Membership
    {
        return $this->authorMembership;
    }

    public function setAuthorMembership(?Membership $authorMembership): static
    {
        $this->authorMembership = $authorMembership;

        return $this;
    }

    public function getChild(): ?Child
    {
        return $this->child;
    }

    public function setChild(?Child $child): static
    {
        $this->child = $child;

        return $this;
    }

    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getStatus(): ExchangeStatus
    {
        return $this->status;
    }

    public function setStatus(ExchangeStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDecidedOption(): ?ExchangeOption
    {
        return $this->decidedOption;
    }

    public function setDecidedOption(?ExchangeOption $decidedOption): static
    {
        $this->decidedOption = $decidedOption;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function isDecision(): bool
    {
        return ExchangeType::Decision === $this->type;
    }

    public function isOpen(): bool
    {
        return ExchangeStatus::Open === $this->status;
    }
}
