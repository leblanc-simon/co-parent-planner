<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps de `POST /api/admin/step-up` : ré-authentification par mot de passe pour l'auth renforcée. */
final class StepUpRequest
{
    #[Assert\NotBlank]
    public string $password = '';
}
