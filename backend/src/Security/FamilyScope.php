<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\DomainAccessDeniedException;
use App\Exception\FamilyAccessDeniedException;
use App\Exception\FamilyScopeMismatchException;
use App\Exception\FamilyScopeRequiredException;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * Résout et autorise la **famille active** (scope X-Family-Id — §3.4) pour les State
 * Providers/Processors d'API Platform, qui ne bénéficient pas du {@see FamilyContextResolver}
 * (réservé aux arguments de contrôleur). Source unique de la logique de scope.
 */
final class FamilyScope
{
    public const string HEADER = 'X-Family-Id';

    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Contexte de la famille active de la requête courante (header X-Family-Id).
     *
     * @throws FamilyScopeRequiredException header absent/malformé
     * @throws FamilyScopeMismatchException {id} du chemin ≠ header
     * @throws FamilyAccessDeniedException  non membre actif (anti-énumération)
     */
    public function require(): FamilyContext
    {
        $request = $this->requestStack->getCurrentRequest();
        \assert($request instanceof Request);

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new FamilyAccessDeniedException();
        }

        return $this->resolve($request, $user);
    }

    /** Variante exploitable depuis le {@see FamilyContextResolver} (argument de contrôleur). */
    public function resolve(Request $request, User $user): FamilyContext
    {
        $raw = $request->headers->get(self::HEADER);
        if (null === $raw || '' === $raw) {
            throw new FamilyScopeRequiredException();
        }
        if (!Uuid::isValid($raw)) {
            throw new FamilyScopeRequiredException('Le header X-Family-Id doit être un UUID valide.');
        }

        $pathFamilyId = $this->familyIdFromPath($request->getPathInfo());
        if (null !== $pathFamilyId && 0 !== strcasecmp($pathFamilyId, $raw)) {
            throw new FamilyScopeMismatchException();
        }

        $membership = $this->memberships->findActive($user, Uuid::fromString($raw));
        if (null === $membership) {
            throw new FamilyAccessDeniedException();
        }

        return new FamilyContext($membership->getFamily(), $membership);
    }

    /**
     * Exige un droit de domaine sur la famille active, via les Voters (§3.1, §6.3).
     *
     * @throws DomainAccessDeniedException si le niveau requis n'est pas accordé
     */
    public function denyUnless(FamilyContext $context, RightDomain $domain, RightLevel $level): void
    {
        $attribute = Voter\AbstractDomainVoter::attribute($domain, $level);
        if (!$this->security->isGranted($attribute, $context)) {
            throw new DomainAccessDeniedException($domain, $level);
        }
    }

    private function familyIdFromPath(string $path): ?string
    {
        return preg_match('#^/api/families/([^/]+)#', $path, $m) ? $m[1] : null;
    }
}
