<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Repository\ExchangeReadRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Dernière lecture d'un échange **par un membre** (revue UX des échanges, X5).
 *
 * Aucune notion de lecture n'existait : le badge de navigation comptait les échanges *ouverts*, pas
 * ceux qui avaient du nouveau, et il fallait rouvrir chaque fil pour savoir s'il avait bougé — sur
 * un écran conversationnel, c'est le manque le plus sensible à l'usage quotidien.
 *
 * `lastReadAt` **entre au registre** : un horodatage de lecture dit quand une personne identifiée
 * était devant son écran. C'est peu, mais ce n'est pas rien, et la règle du projet est d'inscrire
 * plutôt que d'arbitrer au cas par cas.
 */
#[ORM\Entity(repositoryClass: ExchangeReadRepository::class)]
#[ORM\Table(name: 'exchange_reads')]
#[ORM\UniqueConstraint(name: 'uniq_exchange_read', columns: ['membership_id', 'exchange_id'])]
#[ORM\HasLifecycleCallbacks]
class ExchangeRead
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Membership $membership;

    #[ORM\ManyToOne(targetEntity: Exchange::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Exchange $exchange;

    #[PersonalData(
        category: DataCategory::Technical,
        purpose: 'Distinguer les échanges qui ont du nouveau de ceux déjà lus.',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: "Durée de vie de l'échange ou du membership (CASCADE) ; purge RGPD (Lot 8).",
        recipients: 'Interne (usage serveur) — jamais exposé aux autres membres.',
    )]
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastReadAt;

    /**
     * Dernier message lu — **la** référence du marqueur, plutôt qu'une comparaison de dates.
     *
     * Les horodatages du schéma sont à la seconde : une réponse postée dans la même seconde qu'une
     * lecture passait pour déjà lue. Un identifiant de message est exact, et les `Uuid::v7` sont
     * ordonnés dans le temps, donc `MAX(id)` désigne bien le dernier. `null` = échange lu alors
     * qu'il ne portait encore aucun message.
     */
    #[ORM\ManyToOne(targetEntity: Message::class)]
    #[ORM\JoinColumn(name: 'last_read_message_id', nullable: true, onDelete: 'SET NULL')]
    private ?Message $lastReadMessage = null;

    public function __construct(Membership $membership, Exchange $exchange, \DateTimeImmutable $lastReadAt)
    {
        $this->id = Uuid::v7();
        $this->membership = $membership;
        $this->exchange = $exchange;
        $this->lastReadAt = $lastReadAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getExchange(): Exchange
    {
        return $this->exchange;
    }

    public function getLastReadAt(): \DateTimeImmutable
    {
        return $this->lastReadAt;
    }

    public function setLastReadAt(\DateTimeImmutable $lastReadAt): self
    {
        $this->lastReadAt = $lastReadAt;

        return $this;
    }

    public function getLastReadMessage(): ?Message
    {
        return $this->lastReadMessage;
    }

    public function setLastReadMessage(?Message $message): self
    {
        $this->lastReadMessage = $message;

        return $this;
    }
}
