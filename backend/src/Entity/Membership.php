<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\MembershipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Lien Utilisateur ↔ Famille avec rôle et droits (cdc §2.2). Sert au scope X-Family-Id et aux Voters.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'memberships')]
#[ORM\UniqueConstraint(name: 'uniq_membership_user_family', columns: ['user_id', 'family_id'])]
// 1 ↔ 1 : deux beaux-parents ne peuvent pas désigner le même parent (E1). L'unicité du porteur est
// acquise par construction — une ligne par membership.
#[ORM\UniqueConstraint(name: 'uniq_membership_partner', columns: ['partner_membership_id'])]
#[ORM\HasLifecycleCallbacks]
class Membership
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Family::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[ORM\Column(length: 16, enumType: MembershipRole::class)]
    private MembershipRole $role;

    #[ORM\Column(options: ['default' => false])]
    private bool $isAdmin = false;

    /**
     * Profil enfant rattaché (compte-enfant). Colonne UUID nullable SANS contrainte FK au Lot 0
     * (la table Child n'existe pas encore ; FK ajoutée au Lot 2 — décision D4 §0.5). Toujours NULL au Lot 0.
     */
    #[ORM\Column(type: UuidType::NAME, nullable: true)]
    private ?Uuid $childId = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $displayColor = null;

    /**
     * Parent que ce beau-parent accompagne dans la famille (évolution E1, `specs/evolutions-2026-09.md`).
     *
     * Portée par le **beau-parent** : source de vérité unique. Écrire la relation des deux côtés
     * imposerait de tenir deux colonnes cohérentes à chaque mutation, et la première divergence
     * serait invisible. La vue inverse (« Sophie · conjointe : Bob ») se déduit par requête.
     *
     * `SET NULL` au retrait du parent : l'association disparaît, le membre reste.
     */
    #[PersonalData(
        category: DataCategory::Identity,
        purpose: 'Situer un beau-parent par rapport au parent qu\'il accompagne (affichage, foyer de garde, droits proposés).',
        legalBasis: 'Exécution du contrat (coordination du foyer recomposé).',
        retention: 'Durée de vie du membership ; effacée avec lui (SET NULL au retrait du parent visé).',
        recipients: 'Membres de la famille habilités à voir la liste des membres.',
    )]
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'partner_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $partnerMembership = null;

    #[ORM\Column(length: 16, enumType: MembershipStatus::class, options: ['default' => 'active'])]
    private MembershipStatus $status = MembershipStatus::Active;

    /** @var Collection<int, Right> */
    #[ORM\OneToMany(targetEntity: Right::class, mappedBy: 'membership', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $rights;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->rights = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getRole(): MembershipRole
    {
        return $this->role;
    }

    public function setRole(MembershipRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): static
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    public function getChildId(): ?Uuid
    {
        return $this->childId;
    }

    public function setChildId(?Uuid $childId): static
    {
        $this->childId = $childId;

        return $this;
    }

    public function getPartnerMembership(): ?self
    {
        return $this->partnerMembership;
    }

    public function setPartnerMembership(?self $partner): self
    {
        $this->partnerMembership = $partner;

        return $this;
    }

    public function getDisplayColor(): ?string
    {
        return $this->displayColor;
    }

    public function setDisplayColor(?string $color): static
    {
        $this->displayColor = $color;

        return $this;
    }

    public function getStatus(): MembershipStatus
    {
        return $this->status;
    }

    public function setStatus(MembershipStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return MembershipStatus::Active === $this->status;
    }

    /** @return Collection<int, Right> */
    public function getRights(): Collection
    {
        return $this->rights;
    }

    public function addRight(Right $right): static
    {
        if (!$this->rights->contains($right)) {
            $this->rights->add($right);
            $right->setMembership($this);
        }

        return $this;
    }

    public function removeRight(Right $right): static
    {
        $this->rights->removeElement($right);

        return $this;
    }

    /** Right matérialisé pour un domaine donné, le cas échéant. */
    public function rightFor(RightDomain $domain): ?Right
    {
        foreach ($this->rights as $right) {
            if ($right->getDomain() === $domain) {
                return $right;
            }
        }

        return null;
    }

    /**
     * Niveau effectif sur un domaine (table de vérité §2.2 + §3.7) :
     * - **précondition** : un membership non actif (invité/retiré) n'a aucun droit (§3.1) ;
     * - family_settings : édition réservée aux admins ; un parent non-admin n'a que `view`, les autres `none` ;
     * - parent → `edit` forcé sur tous les autres domaines ;
     * - child → `expenses` et structure (`family_settings`) forcés `none` ;
     * - step_parent / child → niveau du Right déclaré (défaut `none`).
     */
    public function effectiveLevel(RightDomain $domain): RightLevel
    {
        // Précondition (§3.1) : aucun droit pour un membership non actif — défense en profondeur,
        // indépendante du pré-filtrage des appelants.
        if (!$this->isActive()) {
            return RightLevel::None;
        }

        // Garde-fou structure : l'édition family_settings exige le flag admin.
        if ($domain->requiresAdminToEdit() && !$this->isAdmin) {
            return MembershipRole::Parent === $this->role ? RightLevel::View : RightLevel::None;
        }

        if ($this->role->forcesEdit()) {
            return RightLevel::Edit;
        }

        if ($this->role->isChild() && ($domain->isForbiddenForChild() || $domain->isStructural())) {
            return RightLevel::None;
        }

        // step_parent : `expenses` inerte tant que le module Dépenses n'est pas activé (§3.1, v2).
        if ($domain->isV2() && !$this->family->isExpensesEnabled()) {
            return RightLevel::None;
        }

        foreach ($this->rights as $right) {
            if ($right->getDomain() === $domain) {
                return $right->getLevel();
            }
        }

        return RightLevel::None;
    }

    public function can(RightDomain $domain, RightLevel $required): bool
    {
        return $this->effectiveLevel($domain)->satisfies($required);
    }

    /**
     * Matrice des niveaux effectifs par domaine (pour le rendu UI, sans appel d'enum en Twig).
     *
     * @return array<string, string> domaine → niveau
     */
    public function effectiveRights(): array
    {
        $map = [];
        foreach (RightDomain::cases() as $domain) {
            $map[$domain->value] = $this->effectiveLevel($domain)->value;
        }

        return $map;
    }
}
