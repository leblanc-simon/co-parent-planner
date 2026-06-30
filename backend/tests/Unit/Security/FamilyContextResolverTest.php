<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\FamilyAccessDeniedException;
use App\Exception\FamilyScopeRequiredException;
use App\Repository\MembershipRepository;
use App\Security\FamilyContext;
use App\Security\FamilyContextResolver;
use App\Security\FamilyScope;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Uid\Uuid;

/**
 * Vérifie la validation du scope X-Family-Id (§3.6) : header requis, UUID valide, membership actif,
 * anti-énumération (403 indistinct). Mécanisme posé ET testé (DoD Lot 0).
 */
final class FamilyContextResolverTest extends TestCase
{
    private function metadata(): ArgumentMetadata
    {
        return new ArgumentMetadata('ctx', FamilyContext::class, false, false, null);
    }

    private function resolver(?User $user, ?Membership $membership): FamilyContextResolver
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $repo = $this->createStub(MembershipRepository::class);
        $repo->method('findActive')->willReturn($membership);

        $scope = new FamilyScope($security, $repo, $this->createStub(RequestStack::class));

        return new FamilyContextResolver($security, $scope);
    }

    private function request(?string $familyId): Request
    {
        $request = new Request();
        if (null !== $familyId) {
            $request->headers->set(FamilyContextResolver::HEADER, $familyId);
        }

        return $request;
    }

    public function testIgnoresUnrelatedArguments(): void
    {
        $resolver = $this->resolver(new User(), null);
        $metadata = new ArgumentMetadata('x', 'string', false, false, null);

        self::assertSame([], iterator_to_array($resolver->resolve($this->request(null), $metadata)));
    }

    public function testMissingHeaderIsRejected(): void
    {
        $resolver = $this->resolver(new User(), null);

        $this->expectException(FamilyScopeRequiredException::class);
        iterator_to_array($resolver->resolve($this->request(null), $this->metadata()));
    }

    public function testMalformedUuidIsRejected(): void
    {
        $resolver = $this->resolver(new User(), null);

        $this->expectException(FamilyScopeRequiredException::class);
        iterator_to_array($resolver->resolve($this->request('not-a-uuid'), $this->metadata()));
    }

    public function testNonMemberIsForbidden(): void
    {
        // UUID valide mais aucun membership actif → 403 indistinct (anti-énumération).
        $resolver = $this->resolver(new User(), null);

        $this->expectException(FamilyAccessDeniedException::class);
        iterator_to_array($resolver->resolve($this->request(Uuid::v7()->toRfc4122()), $this->metadata()));
    }

    public function testActiveMembershipResolvesContext(): void
    {
        $family = new Family();
        $membership = (new Membership())->setUser(new User())->setFamily($family);
        $resolver = $this->resolver($membership->getUser(), $membership);

        $resolved = iterator_to_array($resolver->resolve($this->request(Uuid::v7()->toRfc4122()), $this->metadata()));

        self::assertCount(1, $resolved);
        self::assertInstanceOf(FamilyContext::class, $resolved[0]);
        self::assertSame($family, $resolved[0]->family);
        self::assertSame($membership, $resolved[0]->membership);
    }
}
