<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;
use App\Enum\ExpenseCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * Dépenses d'une famille, filtrées (§2 US-DEP-3) et triées par date décroissante — tri
     * départagé par identifiant pour rester déterministe à dates égales (pagination stable).
     *
     * Aucun `ORDER BY`/`WHERE` sur un champ chiffré : `title` n'est pas 🔒 au Lot 10, mais on ne trie
     * jamais dessus non plus (l'écran ordonne par date, cdc §3.7).
     *
     * @return list<Expense>
     */
    public function findByFamily(
        Family $family,
        ?ExpenseCategory $category = null,
        ?Child $child = null,
        ?bool $settled = null,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->setParameter('family', $family)
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if (null !== $category) {
            $qb->andWhere('e.category = :category')->setParameter('category', $category);
        }
        if (null !== $child) {
            $qb->innerJoin('e.children', 'c')->andWhere('c = :child')->setParameter('child', $child);
        }
        if (null !== $settled) {
            $qb->andWhere('e.settled = :settled')->setParameter('settled', $settled);
        }

        /** @var list<Expense> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Dépenses d'une famille pour les calculs d'agrégat (§3.2/§3.3). Les parts et le payeur sont
     * chargés en une fois : le solde parcourt toutes les dépenses, une jointure paresseuse y
     * déclencherait une requête par ligne.
     *
     * @return list<Expense>
     */
    public function findForAggregates(Family $family): array
    {
        /** @var list<Expense> $result */
        $result = $this->createQueryBuilder('e')
            ->addSelect('s')
            ->leftJoin('e.shares', 's')
            ->andWhere('e.family = :family')
            ->setParameter('family', $family)
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Dépenses où l'utilisateur est **partie prenante** : payeur, auteur de la saisie, ou porteur
     * d'une part. Sert à l'export RGPD (Lot 10 §4.9) — une dépense qu'il a payée ou qu'il doit le
     * concerne, même s'il ne l'a pas saisie.
     *
     * @return list<Expense>
     */
    public function findInvolvingUser(User $user): array
    {
        /** @var list<Expense> $result */
        $result = $this->createQueryBuilder('e')
            ->leftJoin('e.shares', 's')
            ->leftJoin('e.payerMembership', 'p')
            ->leftJoin('e.createdByMembership', 'c')
            ->leftJoin('s.membership', 'sm')
            ->andWhere('p.user = :user OR c.user = :user OR sm.user = :user')
            ->setParameter('user', $user)
            ->distinct()
            ->orderBy('e.expenseDate', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
