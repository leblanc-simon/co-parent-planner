<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Repository\NoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Note partagée rattachée à un enfant (cdc §2.3, Lot 2). `text` **chiffré au repos** 🔒
 * (AES-256-GCM applicatif) ; un dump SQL brut ne révèle pas le contenu.
 */
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'notes')]
#[ORM\Index(name: 'idx_note_child_date', columns: ['child_id', 'note_date', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class Note
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Child::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Child $child;

    /**
     * Auteur (membership). **Nullable + ON DELETE SET NULL** : au retrait d'un membre, ses notes
     * sont **préservées** (auteur anonymisé « membre supprimé ») au lieu d'être détruites — invariant
     * de rétention Lot 2 §4.8/§5.2. (L'anonymisation riche viendra au Lot 8 ; ici on évite la perte
     * de contenu familial via un CASCADE destructeur.).
     */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Membership $authorMembership = null;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Partager une information libre sur l\'enfant entre co-parents.',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: 'Durée de vie de la note ; anonymisation de l\'auteur / purge RGPD (Lot 8).',
        recipients: 'Membres habilités de la famille (children_notes ≥ view).',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $text;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $noteDate;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getChild(): Child
    {
        return $this->child;
    }

    public function setChild(Child $child): static
    {
        $this->child = $child;

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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getNoteDate(): \DateTimeImmutable
    {
        return $this->noteDate;
    }

    public function setNoteDate(\DateTimeImmutable $noteDate): static
    {
        $this->noteDate = $noteDate;

        return $this;
    }
}
