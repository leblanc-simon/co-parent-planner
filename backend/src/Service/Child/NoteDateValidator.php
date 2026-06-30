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
        $tz = new \DateTimeZone($timezone);
        $today = $this->clock->now()->setTimezone($tz)->format('Y-m-d');
        $date = $noteDate->setTimezone($tz)->format('Y-m-d');

        if ($date > $today) {
            throw new ChildInputException('note.error.date_future');
        }

        $birth = $child->getBirthDate();
        if (null !== $birth && $date < $birth->setTimezone($tz)->format('Y-m-d')) {
            throw new ChildInputException('note.error.date_before_birth');
        }
    }
}
