<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Invitation à rejoindre une famille (cdc §5.2). Squelette Lot 0 : acceptation minimale (US-ONB-1) ;
 * émission complète au Lot 1. Token **haché** ; e-mail chiffré 🔒 (pas d'unicité sur le ciphertext).
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table(name: 'invitations')]
#[ORM\Index(name: 'idx_invitation_token', columns: ['token_hash'])]
#[ORM\Index(name: 'idx_invitation_family_email', columns: ['family_id', 'email_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_invitation_pending', columns: ['family_id', 'email_hash'], options: ['where' => "((status)::text = 'pending'::text)"])]
#[ORM\HasLifecycleCallbacks]
class Invitation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class, inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[PersonalData(
        category: DataCategory::Contact,
        purpose: 'Adresser une invitation nominative à rejoindre une famille.',
        legalBasis: 'Intérêt légitime (coordination familiale) ; consentement de l\'invité à l\'acceptation.',
        retention: 'Jusqu\'à acceptation/expiration ; purge des invitations périmées (Lot 8).',
        recipients: 'Invité destinataire ; membres habilités de la famille.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $email;

    /**
     * Empreinte déterministe keyée de l'e-mail (blind index, §5.1) : permet l'unicité
     * « un seul pending par (famille, email) » sans déchiffrer le ciphertext. Jamais réversible sans clé.
     */
    #[PersonalData(
        category: DataCategory::Contact,
        purpose: 'Index aveugle (HMAC) de l\'e-mail invité — unicité d\'une invitation pending sans déchiffrer.',
        legalBasis: 'Intérêt légitime (intégrité des invitations).',
        retention: 'Identique à l\'invitation (jusqu\'à acceptation/expiration ; purge Lot 8).',
        recipients: 'Interne (jamais exposé).',
        encrypted: false,
    )]
    #[ORM\Column(length: 64)]
    private string $emailHash;

    #[ORM\Column(length: 16, enumType: MembershipRole::class)]
    private MembershipRole $role;

    /** @var array<string, string> domaine → niveau proposé */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $proposedRights = [];

    /** Admin proposé — honoré uniquement si `role = parent` (invariant §3.7). */
    #[ORM\Column(options: ['default' => false])]
    private bool $proposedAdmin = false;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(length: 16, enumType: InvitationStatus::class, options: ['default' => 'pending'])]
    private InvitationStatus $status = InvitationStatus::Pending;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getEmailHash(): string
    {
        return $this->emailHash;
    }

    public function setEmailHash(string $emailHash): static
    {
        $this->emailHash = $emailHash;

        return $this;
    }

    public function getRole(): MembershipRole
    {
        return $this->role;
    }

    public function setRole(MembershipRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    /** @return array<string, string> */
    public function getProposedRights(): array
    {
        return $this->proposedRights;
    }

    /** @param array<string, string> $rights */
    public function setProposedRights(array $rights): static
    {
        $this->proposedRights = $rights;

        return $this;
    }

    public function isProposedAdmin(): bool
    {
        return $this->proposedAdmin;
    }

    public function setProposedAdmin(bool $proposedAdmin): static
    {
        $this->proposedAdmin = $proposedAdmin;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getStatus(): InvitationStatus
    {
        return $this->status;
    }

    public function setStatus(InvitationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= Clock::get()->now();
    }

    /** Acceptable : statut pending ET non expirée. */
    public function isAcceptable(): bool
    {
        return $this->status->isAcceptable() && !$this->isExpired();
    }
}
