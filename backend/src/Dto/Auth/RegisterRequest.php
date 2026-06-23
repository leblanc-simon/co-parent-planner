<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageResponse;
use App\Security\Auth\PasswordPolicy;
use App\State\Auth\RegisterProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-1 — inscription (anti-énumération, e-mail de vérification async, aucun token). */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/register',
            status: 202,
            output: MessageResponse::class,
            read: false,
            processor: RegisterProcessor::class,
        ),
    ],
)]
final class RegisterRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: PasswordPolicy::MIN_LENGTH, max: PasswordPolicy::MAX_LENGTH)]
    public string $password = '';

    #[Assert\Length(max: 200)]
    public ?string $fullName = null;

    #[Assert\Length(max: 40)]
    public ?string $phone = null;

    #[Assert\Locale(canonicalize: true)]
    #[Assert\Length(max: 16)]
    public ?string $locale = null;
}
