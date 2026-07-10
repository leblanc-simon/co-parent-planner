<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Admin\StepUpProcessor;

/**
 * Auth renforcée admin technique (cdc §6.2). `POST /api/admin/step-up` (ROLE_ADMIN requis) : re-saisie
 * du mot de passe → grant step-up court à présenter dans l'en-tête `X-Admin-Step-Up` sur `/api/admin/rgpd/*`.
 */
#[ApiResource(
    shortName: 'AdminStepUp',
    operations: [
        new Post(
            uriTemplate: '/admin/step-up',
            status: 200,
            input: StepUpRequest::class,
            read: false,
            processor: StepUpProcessor::class,
        ),
    ],
)]
class AdminStepUp
{
    public string $token = '';
    public int $expiresIn = 0;

    public function __construct(string $token = '', int $expiresIn = 0)
    {
        $this->token = $token;
        $this->expiresIn = $expiresIn;
    }
}
