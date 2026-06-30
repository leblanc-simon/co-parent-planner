<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Family\FamilyResource;
use App\Dto\Invitation\AcceptInvitationRequest;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Enum\MembershipStatus;
use App\Exception\AlreadyMemberException;
use App\Exception\InvalidInvitationException;
use App\Exception\InvitationEmailMismatchException;
use App\Exception\InvitationExpiredException;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\TokenHasher;
use App\Security\Membership\MembershipWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-INV-3 / US-ONB-1b (§3.3 C) — consomme une invitation et crée le Membership (rôle + droits
 * normalisés ; admin honoré uniquement pour un parent). Usage unique, expirant, non énumérant.
 * Sérialise les acceptations concurrentes (verrou sur l'invitation). Authentification requise.
 *
 * @implements ProcessorInterface<AcceptInvitationRequest, FamilyResource>
 */
final class AcceptInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly InvitationRepository $invitations,
        private readonly MembershipRepository $memberships,
        private readonly MembershipWriter $writer,
        private readonly TokenHasher $hasher,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyResource
    {
        \assert($data instanceof AcceptInvitationRequest);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $tokenHash = $this->hasher->hash($data->token);

        $membership = $this->em->wrapInTransaction(function () use ($tokenHash, $user): Membership {
            $invitation = $this->invitations->findOneByTokenHashForUpdate($tokenHash);

            // Introuvable / révoquée / déjà acceptée → réponse non énumérante (400 générique).
            if (null === $invitation || InvitationStatus::Pending !== $invitation->getStatus()) {
                throw new InvalidInvitationException();
            }
            // Le marquage `expired` est assuré par la purge planifiée (§3.3.E) — ici on refuse seulement.
            if ($invitation->getExpiresAt() <= Clock::get()->now()) {
                throw new InvitationExpiredException();
            }
            // Nominative : l'e-mail du compte connecté doit correspondre (sc.5).
            if (mb_strtolower(trim($invitation->getEmail())) !== $user->getEmail()) {
                throw new InvitationEmailMismatchException();
            }

            return $this->createMembership($invitation, $user);
        });

        $this->audit->log(
            AuditAction::MembershipCreated,
            $user,
            $membership->getFamily(),
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['via' => 'invitation'],
            entityType: 'Membership',
            entityId: (string) $membership->getId(),
        ); // flush

        return FamilyResource::fromMembership($membership);
    }

    private function createMembership(Invitation $invitation, User $user): Membership
    {
        $family = $invitation->getFamily();

        if (null !== $this->memberships->findOneBy(['user' => $user, 'family' => $family])) {
            throw new AlreadyMemberException();
        }

        $membership = (new Membership())
            ->setUser($user)
            ->setFamily($family)
            ->setStatus(MembershipStatus::Active);

        // Droits normalisés selon le rôle ; admin honoré seulement si role=parent (garanti à la création).
        $this->writer->apply($membership, $invitation->getRole(), $invitation->isProposedAdmin(), $invitation->getProposedRights());

        $this->em->persist($membership);
        $invitation->setStatus(InvitationStatus::Accepted);

        return $membership;
    }
}
