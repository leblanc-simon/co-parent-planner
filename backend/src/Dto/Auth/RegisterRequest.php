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
    // `Sequentially` : on s'arrête à la première violation d'un champ. Cumulées, `NotBlank` et
    // `Length` produisaient deux messages contradictoires pour une seule cause — « ne doit pas être
    // vide » ET « trop courte » pour un champ laissé vide (revue 2026-08 C12). Les messages sont des
    // clés du catalogue : le libellé générique du validateur ne dit pas la règle du produit.
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'auth.error.email_required'),
        new Assert\Length(max: 255, maxMessage: 'auth.error.email_too_long'),
        new Assert\Email(message: 'auth.error.email_invalid'),
    ])]
    public string $email = '';

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

    #[Assert\Length(max: 200)]
    public ?string $fullName = null;

    #[Assert\Length(max: 40)]
    public ?string $phone = null;

    #[Assert\Locale(canonicalize: true)]
    #[Assert\Length(max: 16)]
    public ?string $locale = null;
}
