<?php

declare(strict_types=1);

namespace App\Dto\Invitation;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Auth\TokenResponse;
use App\State\Invitation\LoginAndAcceptInvitationProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Flux d'invitation simplifié (§3.3), variante *compte existant* : connexion **et** acceptation en
 * un seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
 *
 * Frontière de sécurité : sur un compte préexistant, le **mot de passe est exigé** (2ᵉ facteur) — un
 * lien d'invitation transféré/fuité ne peut pas détourner un compte tiers. L'e-mail est dérivé de
 * l'invitation, jamais du client.
 */
#[ApiResource(
    shortName: 'InvitationOnboarding',
    operations: [
        new Post(
            uriTemplate: '/auth/login-and-accept-invitation',
            status: 200,
            output: TokenResponse::class,
            read: false,
            processor: LoginAndAcceptInvitationProcessor::class,
        ),
    ],
)]
final class LoginAndAcceptInvitationRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    public string $token = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $password = '';
}
