<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\EventCategory;
use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Événement d'agenda (cdc §2.5, §5.2 ; Lot 4). Un événement est **ponctuel** (`seriesId = null`)
 * ou porté par une **série** (le maître porte `recurrence` + un `seriesId` partagé). Les occurrences
 * d'une série ne sont **pas** matérialisées : elles sont calculées à la volée (Lot 4 §3.1). Seuls
 * sont matérialisés, rattachés au `seriesId` :
 *   - les **overrides** d'occurrence (`isOverride = true`, `recurrenceDate` = date d'origine) ;
 *   - les **exclusions** d'occurrence / EXDATE (`cancelled = true`, `recurrenceDate` = date d'origine).
 *
 * Le responsable et l'auteur sont des `Membership` (parent/beau-parent) en `ON DELETE SET NULL` :
 * l'événement survit à l'anonymisation d'un membre (Lot 4 §4.4). Rattachement N-N aux enfants via
 * la table de liaison `event_child` (ManyToMany, `ON DELETE CASCADE`) — exclusif avec `wholeFamily`
 * (§4.6). **Aucun champ typé de santé** : `category=sante` est un simple libellé (§7.6).
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
#[ORM\Index(name: 'idx_event_family_starts', columns: ['family_id', 'starts_at'])]
#[ORM\Index(name: 'idx_event_family_category', columns: ['family_id', 'category'])]
#[ORM\Index(name: 'idx_event_series', columns: ['series_id'])]
// Au plus un override / un EXDATE par (série, date d'origine) — index uniques partiels (§5.3).
#[ORM\UniqueConstraint(name: 'uniq_event_override', columns: ['series_id', 'recurrence_date'], options: ['where' => '(is_override = true)'])]
#[ORM\UniqueConstraint(name: 'uniq_event_cancelled', columns: ['series_id', 'recurrence_date'], options: ['where' => '(cancelled = true)'])]
// Idempotence de création (Idempotency-Key outbox mobile §4.3) : au plus un événement par
// (famille, AUTEUR, clé). L'auteur fait partie du périmètre : sans lui, deux membres employant la
// même valeur de clé se voyaient renvoyer la ressource l'un de l'autre (revue 2026-08 C10).
#[ORM\UniqueConstraint(name: 'uniq_event_idempotency', columns: ['family_id', 'created_by_membership_id', 'idempotency_key'], options: ['where' => '(idempotency_key IS NOT NULL)'])]
#[ORM\HasLifecycleCallbacks]
class Event
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(length: 16, enumType: EventCategory::class)]
    private EventCategory $category = EventCategory::Autre;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'responsible_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $responsibleMembership = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $allDay = false;

    /**
     * Règle de récurrence portée par le maître (§3.1). `null` ⇔ événement ponctuel.
     *
     * @var array{freq: string, until?: string|null, count?: int|null, excludeHolidays?: bool}|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $recurrence = null;

    /** Identifiant de série, partagé par le maître, ses overrides et ses EXDATE. `null` = ponctuel. */
    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $seriesId = null;

    /** Override d'une occurrence (déplacée / modifiée) rattaché à `seriesId`. */
    #[ORM\Column(options: ['default' => false])]
    private bool $isOverride = false;

    /** Date d'origine de l'occurrence (clé d'occurrence) — sur un override / EXDATE (§3.3). */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $recurrenceDate = null;

    /** EXDATE : occurrence exclue individuellement (§3.4). */
    #[ORM\Column(options: ['default' => false])]
    private bool $cancelled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $wholeFamily = false;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'created_by_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $createdByMembership = null;

    /** Clé d'idempotence de création (Idempotency-Key), scoped famille — évite les doublons d'outbox (§4.3). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $idempotencyKey = null;

    /** Verrou optimiste : protège contre l'écrasement concurrent (§4.3). */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /**
     * Enfant(s) rattaché(s) — exclusif avec `wholeFamily` (§4.6). Table de liaison `event_child`.
     *
     * @var Collection<int, Child>
     */
    #[ORM\ManyToMany(targetEntity: Child::class)]
    #[ORM\JoinTable(name: 'event_child')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $children;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->children = new ArrayCollection();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCategory(): EventCategory
    {
        return $this->category;
    }

    public function setCategory(EventCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getResponsibleMembership(): ?Membership
    {
        return $this->responsibleMembership;
    }

    public function setResponsibleMembership(?Membership $responsibleMembership): static
    {
        $this->responsibleMembership = $responsibleMembership;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): static
    {
        $this->allDay = $allDay;

        return $this;
    }

    /**
     * @return array{freq: string, until?: string|null, count?: int|null, excludeHolidays?: bool}|null
     */
    public function getRecurrence(): ?array
    {
        return $this->recurrence;
    }

    /**
     * @param array{freq: string, until?: string|null, count?: int|null, excludeHolidays?: bool}|null $recurrence
     */
    public function setRecurrence(?array $recurrence): static
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getSeriesId(): ?Uuid
    {
        return $this->seriesId;
    }

    public function setSeriesId(?Uuid $seriesId): static
    {
        $this->seriesId = $seriesId;

        return $this;
    }

    public function isOverride(): bool
    {
        return $this->isOverride;
    }

    public function setIsOverride(bool $isOverride): static
    {
        $this->isOverride = $isOverride;

        return $this;
    }

    public function getRecurrenceDate(): ?\DateTimeImmutable
    {
        return $this->recurrenceDate;
    }

    public function setRecurrenceDate(?\DateTimeImmutable $recurrenceDate): static
    {
        $this->recurrenceDate = $recurrenceDate;

        return $this;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function setCancelled(bool $cancelled): static
    {
        $this->cancelled = $cancelled;

        return $this;
    }

    public function isWholeFamily(): bool
    {
        return $this->wholeFamily;
    }

    public function setWholeFamily(bool $wholeFamily): static
    {
        $this->wholeFamily = $wholeFamily;

        return $this;
    }

    public function getCreatedByMembership(): ?Membership
    {
        return $this->createdByMembership;
    }

    public function setCreatedByMembership(?Membership $createdByMembership): static
    {
        $this->createdByMembership = $createdByMembership;

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

    /**
     * Maître d'une série (porte la récurrence) : a un `seriesId`, n'est ni override ni EXDATE.
     */
    public function isSeriesMaster(): bool
    {
        return null !== $this->seriesId && !$this->isOverride && !$this->cancelled;
    }

    /** @return Collection<int, Child> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Child $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }

        return $this;
    }

    public function removeChild(Child $child): static
    {
        $this->children->removeElement($child);

        return $this;
    }
}
