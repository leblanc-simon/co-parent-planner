<?php

declare(strict_types=1);

namespace App\Entity\Concern;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

/**
 * `created_at` / `updated_at` (tous : cdc §5.2). Stockés en UTC, présentés en Europe/Paris.
 */
trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $now = Clock::get()->now();
        // @phpstan-ignore-next-line initialized on persist
        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchTimestamp(): void
    {
        $this->updatedAt = Clock::get()->now();
    }
}
