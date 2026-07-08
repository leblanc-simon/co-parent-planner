<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\NotificationCategory;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Repository\NotificationDispatchRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Trace technique d'un envoi de notification (Lot 7 §5.2) : porte l'**idempotence** via
 * `notificationKey` unique (une notification par fait × destinataire). Ne contient **aucun** champ 🔒
 * en clair — seulement des identifiants (`payloadRefs`).
 */
#[ORM\Entity(repositoryClass: NotificationDispatchRepository::class)]
#[ORM\Table(name: 'notification_dispatches')]
#[ORM\UniqueConstraint(name: 'uniq_notification_key', columns: ['notification_key'])]
#[ORM\Index(name: 'idx_notification_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class NotificationDispatch
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 128)]
    private string $notificationKey;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_user_id', nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 16, enumType: NotificationCategory::class)]
    private NotificationCategory $category;

    #[ORM\Column(length: 32, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(length: 16, enumType: NotificationStatus::class, options: ['default' => 'pending'])]
    private NotificationStatus $status = NotificationStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(string $notificationKey, User $recipient, NotificationType $type)
    {
        $this->id = Uuid::v7();
        $this->notificationKey = $notificationKey;
        $this->recipient = $recipient;
        $this->type = $type;
        $this->category = $type->category();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNotificationKey(): string
    {
        return $this->notificationKey;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getCategory(): NotificationCategory
    {
        return $this->category;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function setStatus(NotificationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }
}
