<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Family;
use App\Entity\Note;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * Notes d'un enfant, plus récentes d'abord (tri par date de note puis création — jamais sur `text`).
     *
     * @return list<Note>
     */
    public function findByChild(Child $child): array
    {
        /** @var list<Note> $result */
        $result = $this->createQueryBuilder('n')
            ->andWhere('n.child = :child')
            ->setParameter('child', $child)
            ->orderBy('n.noteDate', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Dernières notes d'une famille (encart accueil « Dernières notes »), toutes enfants confondus,
     * les plus récemment créées d'abord. Scopé famille via la relation `child.family` ; l'accès reste
     * gouverné en amont par le droit `children_notes` du membre (granularité famille, pas par enfant).
     *
     * @return list<Note>
     */
    public function findRecentByFamily(Family $family, int $limit = 4): array
    {
        /** @var list<Note> $result */
        $result = $this->createQueryBuilder('n')
            // addSelect du graphe (enfant + auteur + user) : évite le N+1 au rendu de l'encart.
            // leftJoin sur l'auteur car nullable (ON DELETE SET NULL à l'anonymisation).
            ->addSelect('c', 'm', 'u')
            ->join('n.child', 'c')
            ->leftJoin('n.authorMembership', 'm')
            ->leftJoin('m.user', 'u')
            ->andWhere('c.family = :family')
            ->setParameter('family', $family)
            // Même tri sémantique que findByChild (date de note d'abord) → mêmes notes, même ordre partout.
            ->orderBy('n.noteDate', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Notes dont l'utilisateur est l'auteur (export RGPD, portabilité — §3.5).
     *
     * @return list<Note>
     */
    public function findByAuthorUser(User $user): array
    {
        /** @var list<Note> $result */
        $result = $this->createQueryBuilder('n')
            ->join('n.authorMembership', 'm')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.noteDate', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
