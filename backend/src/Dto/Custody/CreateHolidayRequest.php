<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use Symfony\Component\Validator\Constraints as Assert;

/** Création d'une période de vacances manuelle (US-CUST-3). */
final class CreateHolidayRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    public ?string $label = null;

    #[Assert\NotBlank]
    public ?string $startDate = null;

    #[Assert\NotBlank]
    public ?string $endDate = null;
}
