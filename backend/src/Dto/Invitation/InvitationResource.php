<?php

declare(strict_types=1);

namespace App\Dto\Invitation;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\Entity\Invitation;
use App\State\Invitation\CreateInvitationProcessor;
use App\State\Invitation\InvitationsProvider;
use App\State\Invitation\RevokeInvitationProcessor;

/**
 * Vue d'une invitation pour l'admin (US-INV-1/2) : jamais le token en clair. L'e-mail est
 * déchiffré à la volée pour l'admin habilité.
 */
#[ApiResource(
    shortName: 'Invitation',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/invitations',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: InvitationsProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/invitations',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateInvitationRequest::class,
            read: false,
            processor: CreateInvitationProcessor::class,
        ),
        new Delete(
            uriTemplate: '/invitations/{id}',
            output: false,
            read: false,
            processor: RevokeInvitationProcessor::class,
        ),
    ],
)]
class InvitationResource
{
    public string $id = '';
    public string $email = '';
    public string $role = '';
    /** @var array<string, string> */
    public array $proposedRights = [];
    public bool $proposedAdmin = false;
    public string $status = '';
    public ?string $expiresAt = null;
    public ?string $createdAt = null;

    public static function fromInvitation(Invitation $invitation): self
    {
        $dto = new self();
        $dto->id = (string) $invitation->getId();
        $dto->email = $invitation->getEmail();
        $dto->role = $invitation->getRole()->value;
        $dto->proposedRights = $invitation->getProposedRights();
        $dto->proposedAdmin = $invitation->isProposedAdmin();
        $dto->status = $invitation->getStatus()->value;
        $dto->expiresAt = $invitation->getExpiresAt()->format(\DATE_ATOM);
        $dto->createdAt = $invitation->getCreatedAt()->format(\DATE_ATOM);

        return $dto;
    }
}
