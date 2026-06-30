<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use Symfony\Component\Validator\Constraints as Assert;

/** Création d'une exception de garde (US-CUST-4). Bornes/horizon/parent validés par le processor. */
final class CreateExceptionRequest
{
    #[Assert\NotBlank]
    public ?string $startDate = null;

    #[Assert\NotBlank]
    public ?string $endDate = null;

    #[Assert\NotBlank]
    public ?string $parentMembershipId = null;

    #[Assert\Length(max: 200)]
    public ?string $reason = null;
}
