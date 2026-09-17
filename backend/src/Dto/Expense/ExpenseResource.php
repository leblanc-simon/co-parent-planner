<?php

declare(strict_types=1);

namespace App\Dto\Expense;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\Family\FamilyResource;
use App\Entity\Child;
use App\Entity\Expense;
use App\State\Expense\CreateExpenseProcessor;
use App\State\Expense\DeleteExpenseProcessor;
use App\State\Expense\ExpensesProvider;
use App\State\Expense\SendReminderProcessor;
use App\State\Expense\UpdateExpenseProcessor;

/**
 * Dépense partagée exposée (Lot 10 §6 ; cdc §6.2 « Dépenses (v2) »).
 *
 * **Toutes ces opérations n'existent que si la famille a activé le module** (`expensesEnabled`) :
 * sinon 404, y compris pour un parent admin (§3.4). Les montants circulent en **centimes entiers**,
 * jamais en décimal : la mise en forme « 1 234,56 € » appartient aux clients, qui la produisent avec
 * le formateur ICU de la locale.
 *
 * Extensions assumées du cdc §6.2, **validées par le porteur produit le 2026-09-18** : `DELETE`
 * (corriger une saisie erronée) et `POST /reminder` (matérialiser l'action « Envoyer un rappel » du
 * cdc §2.7, qui n'était routée nulle part). La pondération par enfant, elle, a été **écartée** à la
 * même occasion : neutre sur le partage entre parents (§3.1.D), elle n'aurait été observable nulle
 * part — `expense_child` reste donc la table de liaison nue du cdc §5.2.
 */
#[ApiResource(
    shortName: 'Expense',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/expenses',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            // Comme toutes les collections du projet : pas de pagination Hydra. Le format `json`
            // exposé au client mobile ne transporte aucune métadonnée de page — paginer ici
            // tronquerait la liste sans que le client puisse le savoir.
            paginationEnabled: false,
            parameters: [
                'category' => new QueryParameter(
                    description: 'Filtre de catégorie (`sante`, `scolarite`, `activites`, `vetements`, `autre`). Absent = toutes.',
                    schema: ['type' => 'string', 'enum' => ['sante', 'scolarite', 'activites', 'vetements', 'autre']],
                ),
                'childId' => new QueryParameter(
                    description: 'Ne retenir que les dépenses concernant cet enfant de la famille active.',
                    schema: ['type' => 'string', 'format' => 'uuid'],
                ),
                'settled' => new QueryParameter(
                    description: 'Filtre d\'état : `true` (réglées), `false` (non réglées). Absent = les deux.',
                    schema: ['type' => 'boolean'],
                ),
            ],
            provider: ExpensesProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/expenses',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateExpenseRequest::class,
            read: false,
            processor: CreateExpenseProcessor::class,
        ),
        new Patch(
            uriTemplate: '/expenses/{id}',
            input: UpdateExpenseRequest::class,
            read: false,
            processor: UpdateExpenseProcessor::class,
        ),
        new Delete(
            uriTemplate: '/expenses/{id}',
            output: false,
            read: false,
            processor: DeleteExpenseProcessor::class,
        ),
        // Rappel au(x) parent(s) débiteur(s) de CETTE dépense. Sans corps ; l'e-mail part par
        // Messenger, la réponse ne confirme donc que la mise en file (§4.7). 429 pendant le cooldown.
        new Post(
            uriTemplate: '/expenses/{id}/reminder',
            status: 200,
            input: false,
            read: false,
            processor: SendReminderProcessor::class,
        ),
    ],
)]
class ExpenseResource
{
    public string $id = '';
    public string $title = '';
    public string $category = '';
    public ?string $payerMembershipId = null;
    /** Nom d'affichage du payeur ; vide si le compte a été anonymisé (§4.9). */
    public string $payerName = '';
    public int $amountCents = 0;
    public string $currency = 'EUR';
    /** Jour civil de la dépense (AAAA-MM-JJ) — une date, pas un instant (§4.1). */
    public string $expenseDate = '';
    public bool $settled = false;
    /** @var list<string> */
    public array $childIds = [];
    /** @var list<ExpenseShareResource> */
    public array $shares = [];
    /** Dernier rappel envoyé, ISO 8601 ; `null` si aucun. */
    public ?string $reminderSentAt = null;
    /**
     * Instant à partir duquel un nouveau rappel est accepté (ISO 8601), `null` si dès maintenant.
     * Le serveur le calcule : la durée du cooldown est une règle serveur, un client ne peut pas la
     * deviner, et un bouton actif qui répond 429 est un bouton cassé.
     */
    public ?string $reminderAvailableAt = null;

    /**
     * @param array<string, int> $effective identifiant de membership → centimes dus
     */
    public static function fromExpense(
        Expense $expense,
        array $effective,
        callable $memberName,
        ?\DateTimeImmutable $reminderAvailableAt = null,
    ): self {
        $dto = new self();
        $dto->id = (string) $expense->getId();
        $dto->title = $expense->getTitle();
        $dto->category = $expense->getCategory()->value;
        $payer = $expense->getPayerMembership();
        $dto->payerMembershipId = null !== $payer ? (string) $payer->getId() : null;
        $dto->payerName = $memberName($payer);
        $dto->amountCents = $expense->getAmountCents();
        $dto->currency = $expense->getCurrency();
        $dto->expenseDate = $expense->getExpenseDate()->format('Y-m-d');
        $dto->settled = $expense->isSettled();
        $dto->childIds = self::childIds($expense);
        $dto->reminderSentAt = $expense->getReminderSentAt()?->format(\DateTimeInterface::ATOM);
        $dto->reminderAvailableAt = $reminderAvailableAt?->format(\DateTimeInterface::ATOM);

        foreach ($expense->getShares() as $share) {
            $membership = $share->getMembership();
            $line = new ExpenseShareResource();
            $line->membershipId = null !== $membership ? (string) $membership->getId() : '';
            $line->memberName = $memberName($membership);
            $line->shareType = $share->getShareType()->value;
            $line->value = $share->getValue();
            $line->effectiveCents = $effective[$line->membershipId] ?? 0;
            $dto->shares[] = $line;
        }

        return $dto;
    }

    /** @return list<string> */
    private static function childIds(Expense $expense): array
    {
        $ids = array_map(static fn (Child $c): string => (string) $c->getId(), $expense->getChildren()->toArray());
        sort($ids);

        return $ids;
    }
}
