<?php

declare(strict_types=1);

namespace App\State\Family;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Family\CreateFamilyRequest;
use App\Dto\Family\FamilyResource;
use App\Entity\Family;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\FamilyType;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Security\Auth\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-ONB-1 sc.1 — crée la première famille de l'utilisateur et son Membership parent + admin actif.
 * Les droits d'un parent étant forcés `edit`, aucune ligne Right n'est créée (cf. §5.2).
 *
 * @implements ProcessorInterface<CreateFamilyRequest, FamilyResource>
 */
final class CreateFamilyProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyResource
    {
        \assert($data instanceof CreateFamilyRequest);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        // Garanti non-null par la validation (NotBlank) executee avant ce processor.
        \assert(null !== $data->name && null !== $data->type);

        $family = (new Family())
            ->setName(trim($data->name))
            ->setType(FamilyType::from($data->type));

        $membership = (new Membership())
            ->setUser($user)
            ->setFamily($family)
            ->setRole(MembershipRole::Parent)
            ->setIsAdmin(true)
            ->setStatus(MembershipStatus::Active);

        $this->em->persist($family);
        $this->em->persist($membership);

        $this->audit->log(
            AuditAction::FamilyCreated,
            $user,
            $family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'Family',
            entityId: (string) $family->getId(),
        ); // flush

        return FamilyResource::fromMembership($membership);
    }
}
