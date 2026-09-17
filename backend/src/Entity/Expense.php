<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\ExpenseCategory;
use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Dépense partagée (cdc §2.7, §5.2 ; Lot 10). Réservée en v1, **activée en v2** : elle n'existe pour
 * une famille que si `Family.expensesEnabled` (§3.4).
 *
 * Le **montant est en centimes entiers** (`amountCents`) : aucun flottant ne touche une somme
 * d'argent, de la saisie à l'agrégat (§3.1, invariant §7.6). `currency` est figé à `EUR` en v2.
 *
 * Le **payeur est un membre `parent`** (cdc §2.7) : un beau-parent habilité saisit la dépense pour le
 * compte d'un parent, mais n'avance jamais l'argent ni n'entre dans le solde inter-parents. La FK est
 * en `ON DELETE SET NULL` : la dépense survit à l'anonymisation d'un compte (§4.9), le payeur
 * devenant « membre supprimé » — cette dépense sort alors du **solde** (voir
 * {@see \App\Service\Expense\BalanceCalculator}) tout en restant dans les totaux historiques.
 *
 * Rattachement N-N aux enfants via `expense_child` (au moins un enfant, §4.6) : contrairement à
 * l'agenda, **aucun** drapeau « toute la famille » n'existe côté dépenses (décision Lot 10 §4.6).
 * `category=sante` reste une étiquette budgétaire, jamais un champ typé de santé (§7.7).
 */
#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expenses')]
#[ORM\Index(name: 'idx_expense_family_date', columns: ['family_id', 'expense_date'])]
#[ORM\Index(name: 'idx_expense_family_category', columns: ['family_id', 'category'])]
#[ORM\Index(name: 'idx_expense_family_settled', columns: ['family_id', 'settled'])]
#[ORM\HasLifecycleCallbacks]
class Expense
{
    use TimestampableTrait;

    /** Borne haute de saisie (§4.2) : 1 000 000 € en centimes. Protège les agrégats des valeurs aberrantes. */
    public const int MAX_AMOUNT_CENTS = 100_000_000;

    public const int MAX_TITLE_LENGTH = 200;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Family::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Family $family;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Identifier la dépense engagée pour les enfants dans les comptes partagés',
        legalBasis: 'Exécution du contrat (coordination parentale)',
        retention: 'Durée de vie de la dépense ; purge RGPD (Lot 8)',
        recipients: 'Membres habilités de la famille (expenses ≥ view, hors enfants)',
    )]
    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(length: 16, enumType: ExpenseCategory::class)]
    private ExpenseCategory $category = ExpenseCategory::Autre;

    /** Parent ayant avancé l'argent. `null` = membre anonymisé (RGPD) : la dépense sort du solde. */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'payer_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $payerMembership = null;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Calculer le solde « qui doit quoi » entre les parents',
        legalBasis: 'Exécution du contrat (coordination parentale)',
        retention: 'Durée de vie de la dépense ; purge RGPD (Lot 8)',
        recipients: 'Membres habilités de la famille (expenses ≥ view, hors enfants)',
    )]
    #[ORM\Column(type: 'integer')]
    private int $amountCents = 0;

    /** Devise ISO 4217. `EUR` en v2 : le champ est réservé, aucune conversion n'est implémentée (§0.3). */
    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /** Jour civil de la dépense (fuseau famille à l'affichage) — une date, jamais un instant (§4.1). */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $expenseDate;

    #[ORM\Column(options: ['default' => false])]
    private bool $settled = false;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'created_by_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $createdByMembership = null;

    /** Dernier rappel envoyé au débiteur — borne le cooldown anti-spam (§4.9, décision PO : 72 h). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    /**
     * Parts par membre (§3.1). Matérialisées à chaque écriture, y compris le défaut 50/50.
     *
     * @var Collection<int, ExpenseShare>
     */
    #[ORM\OneToMany(targetEntity: ExpenseShare::class, mappedBy: 'expense', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $shares;

    /**
     * Enfant(s) concerné(s) — au moins un (§4.6). Table de liaison `expense_child`.
     *
     * @var Collection<int, Child>
     */
    #[ORM\ManyToMany(targetEntity: Child::class)]
    #[ORM\JoinTable(name: 'expense_child')]
    #[ORM\JoinColumn(name: 'expense_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $children;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->shares = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->expenseDate = new \DateTimeImmutable('today');
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

    public function getCategory(): ExpenseCategory
    {
        return $this->category;
    }

    public function setCategory(ExpenseCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPayerMembership(): ?Membership
    {
        return $this->payerMembership;
    }

    public function setPayerMembership(?Membership $membership): static
    {
        $this->payerMembership = $membership;

        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function setAmountCents(int $amountCents): static
    {
        $this->amountCents = $amountCents;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getExpenseDate(): \DateTimeImmutable
    {
        return $this->expenseDate;
    }

    public function setExpenseDate(\DateTimeImmutable $date): static
    {
        $this->expenseDate = $date;

        return $this;
    }

    public function isSettled(): bool
    {
        return $this->settled;
    }

    public function setSettled(bool $settled): static
    {
        $this->settled = $settled;

        return $this;
    }

    public function getCreatedByMembership(): ?Membership
    {
        return $this->createdByMembership;
    }

    public function setCreatedByMembership(?Membership $membership): static
    {
        $this->createdByMembership = $membership;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $at): static
    {
        $this->reminderSentAt = $at;

        return $this;
    }

    /** @return Collection<int, ExpenseShare> */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(ExpenseShare $share): static
    {
        if (!$this->shares->contains($share)) {
            $this->shares->add($share);
            $share->setExpense($this);
        }

        return $this;
    }

    public function removeShare(ExpenseShare $share): static
    {
        $this->shares->removeElement($share);

        return $this;
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

    public function clearChildren(): static
    {
        $this->children->clear();

        return $this;
    }
}
