<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Child;
use App\Entity\Note;
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
}
