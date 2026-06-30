<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Exception\FamilyAccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Résout l'argument contrôleur `FamilyContext` à partir du header `X-Family-Id` (§3.4, §3.6).
 *
 * Un contrôleur qui type-hint `FamilyContext` déclare ainsi qu'il est **scopé à la famille active**.
 * La logique (header requis, cohérence path/header, membership actif) vit dans {@see FamilyScope} ;
 * ce resolver ne fait que la brancher sur le pipeline de contrôleurs Symfony.
 */
final class FamilyContextResolver implements ValueResolverInterface
{
    public const string HEADER = FamilyScope::HEADER;

    public function __construct(
        private readonly Security $security,
        private readonly FamilyScope $scope,
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

        return [$this->scope->resolve($request, $user)];
    }
}
