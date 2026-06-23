<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Auth\RefreshProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-2 sc.5/6 — rafraîchissement rotatif (token depuis le corps OU le cookie HttpOnly). */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/refresh',
            status: 200,
            output: TokenResponse::class,
            read: false,
            processor: RefreshProcessor::class,
        ),
    ],
)]
final class RefreshRequest
{
    #[Assert\Length(max: 512)]
    public ?string $refreshToken = null;
}
