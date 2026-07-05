<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Repository\ExchangeOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Option d'une décision (cdc §5.2, Lot 6). Une décision porte ≥ 2 options ordonnées par `position`.
 * Supprimée en cascade avec son échange.
 */
#[ORM\Entity(repositoryClass: ExchangeOptionRepository::class)]
#[ORM\Table(name: 'exchange_options')]
#[ORM\UniqueConstraint(name: 'uniq_exchange_option_position', columns: ['exchange_id', 'position'])]
#[ORM\HasLifecycleCallbacks]
class ExchangeOption
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Exchange::class)]
    #[ORM\JoinColumn(name: 'exchange_id', nullable: false, onDelete: 'CASCADE')]
    private Exchange $exchange;

    #[ORM\Column(length: 200)]
    private string $label;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sub = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getExchange(): Exchange
    {
        return $this->exchange;
    }

    public function setExchange(Exchange $exchange): static
    {
        $this->exchange = $exchange;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getSub(): ?string
    {
        return $this->sub;
    }

    public function setSub(?string $sub): static
    {
        $this->sub = $sub;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
