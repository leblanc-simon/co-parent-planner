<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\CitextType;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[PersonalData(
        category: DataCategory::Contact,
        purpose: 'Identifiant de connexion et adresse de contact transactionnelle.',
        legalBasis: 'Exécution du contrat (fourniture du service).',
        retention: 'Durée de vie du compte ; anonymisé à la suppression (Lot 8).',
        recipients: 'Interne uniquement.',
    )]
    /** @var non-empty-string e-mail normalisé (login), non vide par construction (setter). */
    #[ORM\Column(type: CitextType::NAME, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[PersonalData(
        category: DataCategory::Authentication,
        purpose: 'Authentification de l\'utilisateur.',
        legalBasis: 'Exécution du contrat ; obligation de sécurité (art. 32 RGPD).',
        retention: 'Durée de vie du compte.',
        recipients: 'Interne uniquement (jamais exposé).',
    )]
    #[ORM\Column]
    private string $passwordHash;

    #[PersonalData(
        category: DataCategory::Identity,
        purpose: 'Affichage du nom de l\'utilisateur dans la famille.',
        legalBasis: 'Exécution du contrat.',
        retention: 'Durée de vie du compte ; anonymisé à la suppression.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $fullName = null;

    #[PersonalData(
        category: DataCategory::Contact,
        purpose: 'Coordonnée optionnelle de contact.',
        legalBasis: 'Consentement (champ facultatif).',
        retention: 'Durée de vie du compte ; anonymisé à la suppression.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 16, options: ['default' => 'fr-FR'])]
    private string $locale = 'fr-FR';

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 16, enumType: UserStatus::class, options: ['default' => 'pending'])]
    private UserStatus $status = UserStatus::Pending;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'user')]
    private Collection $memberships;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** L'e-mail est normalisé (trim + minuscule) ; l'unicité reste garantie par citext en base. */
    public function setEmail(string $email): static
    {
        $normalized = mb_strtolower(trim($email));
        if ('' === $normalized) {
            throw new \InvalidArgumentException('L\'e-mail ne peut pas être vide.');
        }
        $this->email = $normalized;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $when): static
    {
        $this->emailVerifiedAt = $when;

        return $this;
    }

    public function isEmailVerified(): bool
    {
        return null !== $this->emailVerifiedAt;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $hash): static
    {
        $this->passwordHash = $hash;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $when): static
    {
        $this->lastLoginAt = $when;

        return $this;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function setStatus(UserStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    // --- UserInterface / PasswordAuthenticatedUserInterface ---

    public function getUserIdentifier(): string
    {
        // Un User a toujours un e-mail (garanti par setEmail) ; garde défensive explicite
        // pour honorer le contrat non-empty-string de UserInterface.
        if ('' === $this->email) {
            throw new \LogicException('User sans identifiant e-mail.');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
        // Aucun secret en clair conservé en mémoire.
    }
}
