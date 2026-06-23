<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageResponse;
use App\State\Auth\ForgotPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-2 sc.8 — demande de reset (réponse toujours générique, anti-énumération, rate-limité). */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/password/forgot',
            status: 202,
            output: MessageResponse::class,
            read: false,
            processor: ForgotPasswordProcessor::class,
        ),
    ],
)]
final class ForgotPasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $email = '';
}
