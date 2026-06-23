<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageResponse;
use App\State\Auth\LogoutProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/** US-AUTH-2 sc.7 — déconnexion : révoque le refresh courant (corps/cookie) et efface les cookies. */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            status: 200,
            output: MessageResponse::class,
            read: false,
            processor: LogoutProcessor::class,
        ),
    ],
)]
final class LogoutRequest
{
    #[Assert\Length(max: 512)]
    public ?string $refreshToken = null;
}
