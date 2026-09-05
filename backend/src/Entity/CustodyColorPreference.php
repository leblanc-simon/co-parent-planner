<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\CustodyPalette;
use App\Repository\CustodyColorPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Couleur du planning de garde choisie **par un spectateur, pour un parent observé** (cdc §8.2,
 * évolution B de `specs/evolutions-2026-09.md`).
 *
 * Donnée technique de paramétrage (non personnelle, non chiffrée), comme {@see OverlayPreference} :
 * elle ne dit rien de la personne, seulement comment un écran la peint. Elle n'accorde aucun droit
 * et ne modifie l'affichage de personne d'autre — c'est tout l'objet de la demande.
 *
 * **Clé sur le membership observé, jamais sur le slot A/B** : changer de rythme peut échanger les
 * titulaires de A et de B ; une préférence attachée au slot suivrait la place et non la personne, et
 * la couleur « de Sophie » sauterait sur Marc.
 */
#[ORM\Entity(repositoryClass: CustodyColorPreferenceRepository::class)]
#[ORM\Table(name: 'custody_color_preferences')]
#[ORM\UniqueConstraint(name: 'uniq_custody_color_pref', columns: ['membership_id', 'target_membership_id'])]
#[ORM\HasLifecycleCallbacks]
class CustodyColorPreference
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /** Le spectateur : celui dont l'écran change. */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

    /** Le parent observé, dont la couleur est réattribuée sur cet écran-là. */
    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'target_membership_id', nullable: false, onDelete: 'CASCADE')]
    private Membership $target;

    #[ORM\Column(length: 16, enumType: CustodyPalette::class)]
    private CustodyPalette $palette;

    public function __construct(Membership $membership, Membership $target, CustodyPalette $palette)
    {
        $this->id = Uuid::v7();
        $this->membership = $membership;
        $this->target = $target;
        $this->palette = $palette;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMembership(): Membership
    {
        return $this->membership;
    }

    public function getTarget(): Membership
    {
        return $this->target;
    }

    public function getPalette(): CustodyPalette
    {
        return $this->palette;
    }

    public function setPalette(CustodyPalette $palette): self
    {
        $this->palette = $palette;

        return $this;
    }
}
