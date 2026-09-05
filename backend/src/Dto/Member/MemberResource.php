<?php

declare(strict_types=1);

namespace App\Dto\Member;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use App\Dto\Family\FamilyResource;
use App\Entity\Membership;
use App\Enum\RightDomain;
use App\State\Member\FamilyMembersProvider;
use App\State\Member\RemoveMembershipProcessor;
use App\State\Member\UpdateMembershipProcessor;

/**
 * Vue d'un membre d'une famille (US-MBR-1) : rôle, admin, couleur, matrice de droits **effectifs**,
 * et identité 🔒 déchiffrée à la volée pour les membres habilités (jamais en clair dans un dump).
 */
#[ApiResource(
    shortName: 'Member',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/members',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: FamilyMembersProvider::class,
        ),
        new Patch(
            uriTemplate: '/memberships/{id}',
            input: UpdateMembershipRequest::class,
            read: false,
            processor: UpdateMembershipProcessor::class,
        ),
        new Delete(
            uriTemplate: '/memberships/{id}',
            output: false,
            read: false,
            processor: RemoveMembershipProcessor::class,
        ),
    ],
)]
class MemberResource
{
    public string $id = '';
    public string $userId = '';
    public string $role = '';
    public bool $isAdmin = false;
    public ?string $displayColor = null;
    public ?string $fullName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $childId = null;
    /** @var array<string, string> domaine → niveau effectif */
    public array $rights = [];
    /** Vrai s'il s'agit du membre correspondant à l'utilisateur courant. */
    public bool $self = false;

    /**
     * Couple parent ↔ beau-parent (E1). Le champ est rendu **des deux côtés** : le beau-parent porte
     * la relation, le parent la voit en miroir — sans quoi la moitié des écrans devrait faire la
     * jointure elle-même.
     */
    public ?string $partnerMembershipId = null;
    /** Nom 🔒 déchiffré du conjoint, ou son e-mail à défaut ; `null` sans association. */
    public ?string $partnerName = null;

    /**
     * @param Membership|null $partner conjoint résolu par l'appelant : porté par le beau-parent, il
     *                                 doit être recherché en miroir pour un parent (le provider le
     *                                 fait une fois pour toute la collection)
     */
    public static function fromMembership(Membership $membership, bool $self, ?Membership $partner = null): self
    {
        $user = $membership->getUser();
        $partner ??= $membership->getPartnerMembership();

        $dto = new self();
        $dto->id = (string) $membership->getId();
        $dto->userId = (string) $user->getId();
        $dto->role = $membership->getRole()->value;
        $dto->isAdmin = $membership->isAdmin();
        $dto->displayColor = $membership->getDisplayColor();
        $dto->fullName = $user->getFullName();
        $dto->email = $user->getEmail();
        $dto->phone = $user->getPhone();
        $dto->childId = $membership->getChildId() ? (string) $membership->getChildId() : null;
        $dto->self = $self;
        if (null !== $partner) {
            $dto->partnerMembershipId = (string) $partner->getId();
            $dto->partnerName = $partner->getUser()->getFullName() ?: $partner->getUser()->getEmail();
        }

        $rights = [];
        foreach (RightDomain::cases() as $domain) {
            $rights[$domain->value] = $membership->effectiveLevel($domain)->value;
        }
        $dto->rights = $rights;

        return $dto;
    }
}
