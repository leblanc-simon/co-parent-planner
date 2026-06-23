<?php

declare(strict_types=1);

namespace App\Dto\Invitation;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\State\Invitation\AcceptInvitationProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * US-ONB-1 sc.3-5 — accepter une invitation (squelette Lot 0). Authentification requise :
 * l'e-mail du compte connecté doit correspondre à l'invité. Renvoie la famille rejointe.
 */
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/invitations/accept',
            status: 200,
            output: FamilyResource::class,
            read: false,
            processor: AcceptInvitationProcessor::class,
        ),
    ],
)]
final class AcceptInvitationRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    public string $token = '';
}
