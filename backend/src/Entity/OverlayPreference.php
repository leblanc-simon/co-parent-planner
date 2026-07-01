<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\OverlayView;
use App\Repository\OverlayPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Préférence d'affichage de l'overlay famille liée (Lot 5 §5.2), **par membre, par lien, par vue**.
 * Donnée technique de paramétrage (non personnelle, non chiffrée) ; opt-in explicite (défaut off).
 * L'invalidation d'un lien rend la préférence inopérante (le rendu l'ignore) sans la supprimer.
 */
#[ORM\Entity(repositoryClass: OverlayPreferenceRepository::class)]
#[ORM\Table(name: 'overlay_preferences')]
#[ORM\UniqueConstraint(name: 'uniq_overlay_pref', columns: ['membership_id', 'family_link_id', 'view'])]
#[ORM\Index(name: 'idx_overlay_pref_membership_view', columns: ['membership_id', 'view'])]
#[ORM\HasLifecycleCallbacks]
class OverlayPreference
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

    #[ORM\ManyToOne(targetEntity: FamilyLink::class)]
    #[ORM\JoinColumn(name: 'family_link_id', nullable: false, onDelete: 'CASCADE')]
    private FamilyLink $familyLink;

    #[ORM\Column(length: 16, enumType: OverlayView::class)]
    private OverlayView $view;

    #[ORM\Column(options: ['default' => false])]
    private bool $enabled = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getFamilyLink(): FamilyLink
    {
        return $this->familyLink;
    }

    public function setFamilyLink(FamilyLink $familyLink): static
    {
        $this->familyLink = $familyLink;

        return $this;
    }

    public function getView(): OverlayView
    {
        return $this->view;
    }

    public function setView(OverlayView $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
