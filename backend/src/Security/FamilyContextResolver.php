<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Exception\FamilyAccessDeniedException;
use App\Exception\FamilyScopeRequiredException;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Uid\Uuid;

/**
 * Résout l'argument contrôleur `FamilyContext` à partir du header `X-Family-Id` (§3.6).
 *
 * Un contrôleur qui type-hint `FamilyContext` déclare ainsi qu'il est **scopé à la famille active** :
 *  - header absent/malformé → 400 (anti-énumération neutre) ;
 *  - membership inactif / inexistant / autre utilisateur → 403 indistinct.
 *
 * L'autorisation fine (domaine/niveau) est ensuite déléguée aux Voters.
 */
final class FamilyContextResolver implements ValueResolverInterface
{
    public const string HEADER = 'X-Family-Id';

    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
    ) {
    }

    /**
     * @return iterable<FamilyContext>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (FamilyContext::class !== $argument->getType()) {
            return [];
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new FamilyAccessDeniedException();
        }

        $raw = $request->headers->get(self::HEADER);
        if (null === $raw || '' === $raw) {
            throw new FamilyScopeRequiredException();
        }
        if (!Uuid::isValid($raw)) {
            throw new FamilyScopeRequiredException('Le header X-Family-Id doit être un UUID valide.');
        }

        $membership = $this->memberships->findActive($user, Uuid::fromString($raw));
        if (null === $membership) {
            // Ne distingue pas "famille inexistante" de "pas membre" (anti-énumération).
            throw new FamilyAccessDeniedException();
        }

        return [new FamilyContext($membership->getFamily(), $membership)];
    }
}
