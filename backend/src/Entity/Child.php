<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedJsonType;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Repository\ChildRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Profil d'un enfant (cdc §2.3, Lot 2). Informations pratiques **libres** (paires label/valeur) —
 * **aucun champ médical typé** (invariant « pas de données de santé »).
 *
 * `firstName`, `schoolLabel` et `info` sont **chiffrés au repos** 🔒 (AES-256-GCM applicatif, cdc
 * §5.2) : ce sont des données de **mineurs**, et elles étaient les dernières à rendre un dump SQL
 * brut lisible. Cela révise la décision §5.4 #4, qui les laissait en clair pour conserver le tri
 * serveur — tri désormais assuré par {@see \App\Service\Child\ChildSorter}.
 */
#[ORM\Entity(repositoryClass: ChildRepository::class)]
#[ORM\Table(name: 'children')]
#[ORM\HasLifecycleCallbacks]
class Child
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[PersonalData(
        category: DataCategory::Identity,
        purpose: 'Identifier et afficher l\'enfant dans la famille.',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: 'Durée de vie du profil ; anonymisé/supprimé via le RGPD Manager (Lot 8).',
        recipients: 'Membres habilités de la famille.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $firstName;

    #[ORM\Column(length: 16, options: ['default' => '#C2643D'])]
    private string $color = '#C2643D';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[PersonalData(
        category: DataCategory::Identity,
        purpose: 'Situer l\'enfant (classe, établissement) pour organiser la garde et l\'agenda.',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: 'Durée de vie du profil ; purge RGPD (Lot 8).',
        recipients: 'Membres habilités de la famille.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $schoolLabel = null;

    /**
     * Informations pratiques **libres** : liste ordonnée de paires {label, value}. Aucun preset
     * médical (invariant). L'ordre du tableau EST l'ordre d'affichage.
     *
     * @var list<array{label: string, value: string}>
     */
    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Informations pratiques libres saisies par les parents (hors données de santé).',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: 'Durée de vie du profil ; purge RGPD (Lot 8).',
        recipients: 'Membres habilités de la famille.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedJsonType::NAME)]
    private array $info = [];

    /** Verrou optimiste : protège `info` (jsonb) de l'écrasement concurrent (§4.2). */
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    /** @var Collection<int, Note> */
    #[ORM\OneToMany(targetEntity: Note::class, mappedBy: 'child', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $notes;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->notes = new ArrayCollection();
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

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getSchoolLabel(): ?string
    {
        return $this->schoolLabel;
    }

    public function setSchoolLabel(?string $schoolLabel): static
    {
        $this->schoolLabel = $schoolLabel;

        return $this;
    }

    /** @return list<array{label: string, value: string}> */
    public function getInfo(): array
    {
        return $this->info;
    }

    /** @param list<array{label: string, value: string}> $info */
    public function setInfo(array $info): static
    {
        $this->info = $info;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /** @return Collection<int, Note> */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    /** Initiale d'affichage : premier graphème de `first_name`, en majuscule. */
    public function getInitial(): string
    {
        $first = mb_substr(trim($this->firstName), 0, 1);

        return '' === $first ? '?' : mb_strtoupper($first);
    }
}
