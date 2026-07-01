<?php

declare(strict_types=1);

namespace App\Dto\Agenda;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Édition d'un événement / d'une occurrence / d'une série (US-AG-5/6). Tous les champs sont
 * optionnels : seuls les champs **fournis (non null)** sont appliqués (l'effacement d'un champ à
 * null n'est pas géré en v1). `expectedVersion` arme le verrou optimiste (§4.3 → 409 si périmé).
 */
final class UpdateEventRequest
{
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    public ?string $category = null;

    public ?string $responsibleMembershipId = null;

    /** @var list<string>|null */
    public ?array $childIds = null;

    public ?bool $wholeFamily = null;

    #[Assert\Length(max: 255)]
    public ?string $location = null;

    #[Assert\Length(max: 2000)]
    public ?string $description = null;

    public ?string $startsAt = null;

    public ?string $endsAt = null;

    public ?bool $allDay = null;

    /** @var array<string, mixed>|null {freq, until?, count?, excludeHolidays?} — pour scope=series. */
    public ?array $recurrence = null;

    /** Date d'origine de l'occurrence ciblée (scope=occurrence) ou pivot (scope=series). */
    public ?string $recurrenceDate = null;

    public ?int $expectedVersion = null;
}
