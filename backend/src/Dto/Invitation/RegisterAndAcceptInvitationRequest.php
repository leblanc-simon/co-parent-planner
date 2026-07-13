<?php

declare(strict_types=1);

namespace App\Dto\Invitation;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Auth\TokenResponse;
use App\Security\Auth\PasswordPolicy;
use App\State\Invitation\RegisterAndAcceptInvitationProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Flux d'invitation simplifié (§3.3) : création de compte **et** acceptation de l'invitation en un
 * seul appel. Endpoint **public** (préfixe `/api/auth/*`), gated par un token d'invitation valide.
 *
 * L'e-mail n'est PAS fourni par le client : il est dérivé côté serveur de l'invitation (le token
 * prouve la possession de la boîte → compte créé directement `active`, e-mail vérifié). Une session
 * est ouverte immédiatement (cookies HttpOnly + `TokenResponse` pour le mobile) : aucun e-mail de
 * vérification, aucune reconnexion.
 */
#[ApiResource(
    shortName: 'InvitationOnboarding',
    operations: [
        new Post(
            uriTemplate: '/auth/register-and-accept-invitation',
            status: 200,
            output: TokenResponse::class,
            read: false,
            processor: RegisterAndAcceptInvitationProcessor::class,
        ),
    ],
)]
final class RegisterAndAcceptInvitationRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    public string $token = '';

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
