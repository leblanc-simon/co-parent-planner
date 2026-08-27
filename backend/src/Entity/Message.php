<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Message du fil d'un échange (cdc §2.6, §5.2 ; Lot 6). `text` **chiffré au repos** 🔒
 * (AES-256-GCM applicatif) : un dump SQL brut ne révèle aucun contenu. L'auteur (`Membership`) est en
 * `ON DELETE SET NULL` : le fil survit à l'anonymisation d'un membre (§4.8).
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'idx_message_exchange_sent', columns: ['exchange_id', 'sent_at', 'id'])]
// Idempotence de pose (Idempotency-Key outbox mobile §4.2) : au plus un message par
// (échange, AUTEUR, clé) — l'auteur fait partie du périmètre (revue 2026-08 C10).
#[ORM\UniqueConstraint(name: 'uniq_message_idempotency', columns: ['exchange_id', 'author_membership_id', 'idempotency_key'], options: ['where' => '(idempotency_key IS NOT NULL)'])]
#[ORM\HasLifecycleCallbacks]
class Message
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Exchange::class)]
    #[ORM\JoinColumn(name: 'exchange_id', nullable: false, onDelete: 'CASCADE')]
    private Exchange $exchange;

    #[ORM\ManyToOne(targetEntity: Membership::class)]
    #[ORM\JoinColumn(name: 'author_membership_id', nullable: true, onDelete: 'SET NULL')]
    private ?Membership $authorMembership = null;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Coordination co-parentale : échanger des messages sur un sujet enfant/famille.',
        legalBasis: 'Exécution du contrat (coordination parentale).',
        retention: 'Durée de vie de l\'échange ; anonymisation de l\'auteur / purge RGPD (Lot 8).',
        recipients: 'Membres habilités de la famille (exchanges ≥ view).',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $text;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    /** Clé d'idempotence de pose (Idempotency-Key), scoped échange (§4.2). */
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $idempotencyKey = null;

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

    public function getAuthorMembership(): ?Membership
    {
        return $this->authorMembership;
    }

    public function setAuthorMembership(?Membership $authorMembership): static
    {
        $this->authorMembership = $authorMembership;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }
}
