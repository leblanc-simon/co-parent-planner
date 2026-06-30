<?php

declare(strict_types=1);

namespace App\Service\Child;

use App\Entity\Child;
use App\Exception\ChildInputException;
use Psr\Clock\ClockInterface;

/**
 * Valide la cohérence d'une `note_date` (Lot 2 §3.8) : jamais dans le futur, jamais antérieure à
 * la naissance de l'enfant (si connue). Comparaison en date locale famille via horloge injectable.
 */
final class NoteDateValidator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function validate(\DateTimeImmutable $noteDate, Child $child, string $timezone = 'Europe/Paris'): void
    {
        // « Aujourd'hui » dans le fuseau famille ; note_date et birth_date sont des dates CALENDAIRES
        // (lues telles quelles, sans conversion de fuseau qui les décalerait d'un jour).
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d');
        $date = $noteDate->format('Y-m-d');

        if ($date > $today) {
            throw new ChildInputException('note.error.date_future');
        }

        $birth = $child->getBirthDate();
        if (null !== $birth && $date < $birth->format('Y-m-d')) {
            throw new ChildInputException('note.error.date_before_birth');
        }
    }
}
