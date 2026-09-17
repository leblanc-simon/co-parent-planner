<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\ShareType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Part d'une dépense revenant à un membre (cdc §5.2 ; Lot 10 §3.1, §5.2).
 *
 * La source de vérité est le couple **(`shareType`, `value`) tel que saisi**, jamais un montant
 * dérivé : ré-éditer une dépense doit rendre exactement ce que l'utilisateur avait entré. Le montant
 * effectif en centimes est recalculé à la demande par {@see \App\Service\Expense\ShareCalculator}
 * (méthode du plus grand reste), ce qui garantit `Σ parts == Expense::amountCents` sans jamais
 * stocker un arrondi qui dériverait après une modification du total.
 *
 * `value` est un **entier** dans les deux cas — pourcentage entier de 0 à 100, ou centimes : aucune
 * fraction de centime, aucun flottant (§4.2).
 *
 * La FK membre est en `ON DELETE SET NULL` : la part survit à l'anonymisation d'un compte pour
 * préserver l'historique (§4.9) ; la dépense correspondante sort alors du solde inter-parents.
 */
#[ORM\Entity]
#[ORM\Table(name: 'expense_shares')]
#[ORM\UniqueConstraint(name: 'uniq_expense_share_member', columns: ['expense_id', 'membership_id'])]
#[ORM\HasLifecycleCallbacks]
class ExpenseShare
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Expense::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(name: 'expense_id', nullable: false, onDelete: 'CASCADE')]
    private Expense $expense;

    /** Membre concerné par la part. `null` = membre anonymisé (RGPD) — part conservée, solde neutralisé. */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $membership = null;

    #[ORM\Column(length: 16, enumType: ShareType::class)]
    private ShareType $shareType = ShareType::Percent;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Répartir une dépense entre les parents et calculer le solde',
        legalBasis: 'Exécution du contrat (coordination parentale)',
        retention: 'Durée de vie de la dépense ; purge RGPD (Lot 8)',
        recipients: 'Membres habilités de la famille (expenses ≥ view, hors enfants)',
    )]
    #[ORM\Column(type: 'integer')]
    private int $value = 0;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getExpense(): Expense
    {
        return $this->expense;
    }

    public function setExpense(Expense $expense): static
    {
        $this->expense = $expense;

        return $this;
    }

    public function getMembership(): ?Membership
    {
        return $this->membership;
    }

    public function setMembership(?Membership $membership): static
    {
        $this->membership = $membership;

        return $this;
    }

    public function getShareType(): ShareType
    {
        return $this->shareType;
    }

    public function setShareType(ShareType $type): static
    {
        $this->shareType = $type;

        return $this;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): static
    {
        $this->value = $value;

        return $this;
    }
}
