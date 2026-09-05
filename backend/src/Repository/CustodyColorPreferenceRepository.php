<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CustodyColorPreference;
use App\Entity\Membership;
use App\Enum\CustodyPalette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Préférences de couleur du planning, **du seul spectateur** (cdc §8.2).
 *
 * @extends ServiceEntityRepository<CustodyColorPreference>
 */
class CustodyColorPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustodyColorPreference::class);
    }

    /** @return list<CustodyColorPreference> */
    public function findForViewer(Membership $viewer): array
    {
        return array_values($this->findBy(['membership' => $viewer]));
    }

    public function findOneFor(Membership $viewer, Membership $target): ?CustodyColorPreference
    {
        return $this->findOneBy(['membership' => $viewer, 'target' => $target]);
    }

    /**
     * Palette effective d'un spectateur pour chaque parent du rythme : sa préférence si elle existe,
     * sinon la valeur par défaut du §8 (terracotta pour A, teal pour B).
     *
     * @return array{A: CustodyPalette, B: CustodyPalette}
     */
    public function paletteFor(Membership $viewer, ?Membership $parentA, ?Membership $parentB): array
    {
        $chosen = [];
        foreach ($this->findForViewer($viewer) as $preference) {
            $chosen[(string) $preference->getTarget()->getId()] = $preference->getPalette();
        }

        return [
            'A' => (null !== $parentA ? ($chosen[(string) $parentA->getId()] ?? null) : null) ?? CustodyPalette::DEFAULT_A,
            'B' => (null !== $parentB ? ($chosen[(string) $parentB->getId()] ?? null) : null) ?? CustodyPalette::DEFAULT_B,
        ];
    }
}
