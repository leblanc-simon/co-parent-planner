<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Enum\AuditAction;
use App\Enum\DataCategory;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit **immuable** (append-only) — cdc §5.4, invariant CLAUDE.md.
 * Aucune mise à jour ni suppression : un PreUpdate lève une exception (testé).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_actor', columns: ['actor_user_id'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Family $family = null;

    #[ORM\Column(length: 64, enumType: AuditAction::class)]
    private AuditAction $action;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entityId = null;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Traçabilité et sécurité (détection d\'abus, preuve d\'accès).',
        legalBasis: 'Obligation légale / intérêt légitime (sécurité, art. 32 RGPD).',
        retention: 'Selon la politique de rétention des journaux (Lot 8).',
        recipients: 'Administration technique uniquement.',
    )]
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        AuditAction $action,
        ?User $actorUser = null,
        ?Family $family = null,
        ?string $ip = null,
        array $metadata = [],
        ?string $entityType = null,
        ?string $entityId = null,
    ) {
        $this->id = Uuid::v7();
        $this->action = $action;
        $this->actorUser = $actorUser;
        $this->family = $family;
        $this->ip = $ip;
        $this->metadata = $metadata;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->occurredAt = Clock::get()->now();
    }

    #[ORM\PreUpdate]
    public function preventUpdate(): never
    {
        throw new \LogicException('AuditLog est append-only : toute modification est interdite (cdc §5.4).');
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
