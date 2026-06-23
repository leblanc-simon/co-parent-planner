<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Niveau d'un droit sur un domaine (cdc §2.2). Ordonné : none < view < edit.
 */
enum RightLevel: string
{
    case None = 'none';
    case View = 'view';
    case Edit = 'edit';

    private function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::View => 1,
            self::Edit => 2,
        };
    }

    /** Ce niveau satisfait-il au moins le niveau requis ? */
    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    public function allowsView(): bool
    {
        return $this->satisfies(self::View);
    }

    public function allowsEdit(): bool
    {
        return self::Edit === $this;
    }
}
