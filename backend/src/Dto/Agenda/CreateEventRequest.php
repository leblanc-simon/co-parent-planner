<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création d'un événement ou d'une série (US-AG-1/2). Le format de base (champs requis, longueurs)
 * est validé par le Validator ; la cohérence métier (cible, responsable, intervalle, récurrence,
 * scope famille) est validée par l'`EventWriter` (422).
 */
final class CreateEventRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    #[Assert\NotBlank]
    public ?string $category = null;

    #[Assert\NotBlank]
    public ?string $responsibleMembershipId = null;

    /** @var list<string> */
    public array $childIds = [];

    public bool $wholeFamily = false;

    #[Assert\Length(max: 255)]
    public ?string $location = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public bool $allDay = false;

    /** @var array<string, mixed>|null {freq, until?, count?, excludeHolidays?} */
    public ?array $recurrence = null;
}
