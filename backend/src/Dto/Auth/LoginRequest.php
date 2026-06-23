<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\Auth\LoginProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-2 sc.1 — connexion : JWT + refresh rotatif (rate-limit + lockout, anti-énumération). */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/login',
            status: 200,
            output: TokenResponse::class,
            read: false,
            processor: LoginProcessor::class,
        ),
    ],
)]
final class LoginRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $password = '';
}
