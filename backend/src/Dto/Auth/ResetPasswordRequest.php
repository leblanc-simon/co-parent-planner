<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageResponse;
use App\Security\Auth\PasswordPolicy;
use App\State\Auth\ResetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-2 sc.9 — réinitialisation effective (token usage unique ; révoque tous les refresh). */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/password/reset',
            status: 200,
            output: MessageResponse::class,
            read: false,
            processor: ResetPasswordProcessor::class,
        ),
    ],
)]
final class ResetPasswordRequest
{
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'auth.error.link_incomplete'),
        new Assert\Length(max: 512, maxMessage: 'auth.error.link_incomplete'),
    ])]
    public string $token = '';

    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'auth.error.password_required'),
        new Assert\Length(
            min: PasswordPolicy::MIN_LENGTH,
            max: PasswordPolicy::MAX_LENGTH,
            minMessage: 'auth.error.password_too_short',
            maxMessage: 'auth.error.password_too_long',
        ),
    ])]
    public string $password = '';
}
