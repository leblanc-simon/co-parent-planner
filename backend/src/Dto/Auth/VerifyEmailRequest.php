<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\MessageResponse;
use App\State\Auth\VerifyEmailProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * US-AUTH-1 sc.5 — vérification d'e-mail. Endpoint API unique (décision D5) appelé par la page
 * web GET /verify-email et par le deep link mobile.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/verify-email',
            status: 200,
            output: MessageResponse::class,
            read: false,
            processor: VerifyEmailProcessor::class,
        ),
    ],
)]
final class VerifyEmailRequest
{
    // Le jeton vient du lien e-mail, jamais d'une saisie : « Cette valeur ne doit pas être vide »
    // n'aidait personne (revue C12). On nomme la cause réelle — lien tronqué ou incomplet.
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'auth.error.link_incomplete'),
        new Assert\Length(max: 512, maxMessage: 'auth.error.link_incomplete'),
    ])]
    public string $token = '';
}
