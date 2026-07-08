<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Enum\NotificationCategory;
use App\Enum\NotificationChannel;
use App\Repository\NotificationPrefRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Préférence de notification par catégorie et canal (cdc §5.2). Table posée au Lot 0 ;
 * pilotée au Lot 7 (e-mail) / Lot 10 (push).
 */
#[ORM\Entity(repositoryClass: NotificationPrefRepository::class)]
#[ORM\Table(name: 'notification_prefs')]
#[ORM\UniqueConstraint(name: 'uniq_notifpref', columns: ['user_id', 'category', 'channel'])]
#[ORM\HasLifecycleCallbacks]
class NotificationPref
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: NotificationCategory::class)]
    private NotificationCategory $category;

    #[ORM\Column(length: 8, enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCategory(): NotificationCategory
    {
        return $this->category;
    }

    public function setCategory(NotificationCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function setChannel(NotificationChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
