<?php

declare(strict_types=1);

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\User;
use App\State\Me\MeProcessor;
use App\State\Me\MeProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * US-SET-1 (sous-ensemble Lot 0) — profil du compte courant. `/api/me` est implicitement scopé à
 * l'utilisateur du token (jamais un autre compte). `full_name`/`phone` 🔒 déchiffrés en lecture.
 *
 * `avatarUrl`, `email`, `status`, `emailVerified` sont en lecture seule (groupe `me:read` uniquement) :
 * un PATCH les ignore (cf. §4.5 pour avatar_url).
 */
#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(uriTemplate: '/me', provider: MeProvider::class),
        new Patch(uriTemplate: '/me', provider: MeProvider::class, processor: MeProcessor::class),
    ],
    normalizationContext: ['groups' => ['me:read']],
    denormalizationContext: ['groups' => ['me:write']],
)]
class Me
{
    #[Groups(['me:read'])]
    public ?string $id = null;

    #[Groups(['me:read'])]
    public string $email = '';

    #[Groups(['me:read', 'me:write'])]
    #[Assert\Locale(canonicalize: true)]
    #[Assert\Length(max: 16)]
    public ?string $locale = null;

    #[Groups(['me:read', 'me:write'])]
    #[Assert\Length(max: 200)]
    public ?string $fullName = null;

    #[Groups(['me:read', 'me:write'])]
    #[Assert\Length(max: 40)]
    public ?string $phone = null;

    #[Groups(['me:read'])]
    public ?string $avatarUrl = null;

    #[Groups(['me:read'])]
    public bool $emailVerified = false;

    #[Groups(['me:read'])]
    public string $status = '';

    public static function fromUser(User $user): self
    {
        $me = new self();
        $me->id = (string) $user->getId();
        $me->email = $user->getEmail();
        $me->locale = $user->getLocale();
        $me->fullName = $user->getFullName();
        $me->phone = $user->getPhone();
        $me->avatarUrl = $user->getAvatarUrl();
        $me->emailVerified = $user->isEmailVerified();
        $me->status = $user->getStatus()->value;

        return $me;
    }
}
