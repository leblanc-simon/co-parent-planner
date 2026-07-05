<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Repository\OptionVoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Validation d'une option par un membre habilité (cdc §5.2, Lot 6). **Un seul vote actif par membre
 * et par décision** (unicité `(exchange_id, membership_id)`) : le changement de vote = UPDATE de
 * `option_id`. `exchange_id` est redondant avec l'option mais porte l'unicité et accélère le décompte.
 */
#[ORM\Entity(repositoryClass: OptionVoteRepository::class)]
#[ORM\Table(name: 'option_votes')]
#[ORM\UniqueConstraint(name: 'uniq_option_vote_member', columns: ['exchange_id', 'membership_id'])]
#[ORM\HasLifecycleCallbacks]
class OptionVote
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Exchange::class)]
    #[ORM\JoinColumn(name: 'exchange_id', nullable: false, onDelete: 'CASCADE')]
    private Exchange $exchange;

    #[ORM\ManyToOne(targetEntity: ExchangeOption::class)]
    #[ORM\JoinColumn(name: 'option_id', nullable: false, onDelete: 'CASCADE')]
    private ExchangeOption $option;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'membership_id', nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

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

    public function getOption(): ExchangeOption
    {
        return $this->option;
    }

    public function setOption(ExchangeOption $option): static
    {
        $this->option = $option;

        return $this;
    }

    public function getMembership(): Membership
    {
        return $this->membership;
    }

    public function setMembership(Membership $membership): static
    {
        $this->membership = $membership;

        return $this;
    }
}
