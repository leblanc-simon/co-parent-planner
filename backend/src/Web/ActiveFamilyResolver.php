<?php

declare(strict_types=1);

namespace App\Web;

use App\Entity\Membership;
use App\Entity\User;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Résout la **famille active** côté front web server-rendered (US-SCOPE-1). La sélection est
 * persistée dans un cookie lisible JS (`cpp_family`) posé par le sélecteur ; à défaut, la première
 * famille du membre. Les pages serveur l'utilisent pour rendre le bon contexte ; les appels API du
 * navigateur portent le header `X-Family-Id` correspondant.
 */
final class ActiveFamilyResolver
{
    public const string COOKIE = 'cpp_family';

    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** @return list<Membership> */
    public function memberships(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->memberships->findActiveForUser($user) : [];
    }

    /**
     * Membership de la famille active (cookie si valide, sinon la première), ou null si aucune famille.
     *
     * @param list<Membership> $memberships liste déjà chargée (évite une requête supplémentaire)
     */
    public function active(array $memberships): ?Membership
    {
        if ([] === $memberships) {
            return null;
        }

        $cookie = $this->requestStack->getCurrentRequest()?->cookies->get(self::COOKIE);
        if (\is_string($cookie)) {
            foreach ($memberships as $membership) {
                if ((string) $membership->getFamily()->getId() === $cookie) {
                    return $membership;
                }
            }
        }

        return $memberships[0];
    }
}
