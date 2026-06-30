<?php

declare(strict_types=1);

namespace App\Service\Child;

/** Une ligne du bloc « Accès & droits » d'un enfant (US-CH-2). */
final readonly class AccessEntry
{
    public function __construct(
        public string $membershipId,
        public string $name,
        public string $role,
        public string $level,
        public bool $isOwnProfile,
        public ?string $displayColor,
    ) {
    }
}
