<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Family\FamilyResource;
use App\Dto\Invitation\AcceptInvitationRequest;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\InvalidInvitationException;
use App\Exception\InvitationEmailMismatchException;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\TokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-ONB-1 sc.3-5 — consomme une invitation et crée le Membership (rôle + droits proposés).
 * Idempotent si l'utilisateur est déjà membre. Authentification requise (firewall).
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

        $invitation = $this->invitations->findOneByTokenHash($this->hasher->hash($data->token));
        if (null === $invitation || !$invitation->isAcceptable()) {
            throw new InvalidInvitationException();
        }

        // L'invitation est nominative : l'e-mail du compte connecté doit correspondre (sc.5).
        if (mb_strtolower(trim($invitation->getEmail())) !== $user->getEmail()) {
            throw new InvitationEmailMismatchException();
        }

        $family = $invitation->getFamily();
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        // Acceptation idempotente si déjà membre.
        $existing = $this->memberships->findOneBy(['user' => $user, 'family' => $family]);
        if ($existing instanceof Membership) {
            $invitation->setStatus(InvitationStatus::Accepted);
            $this->audit->log(AuditAction::InvitationAccepted, $user, $family, ip: $ip);

            return FamilyResource::fromMembership($existing);
        }

        $membership = (new Membership())
            ->setUser($user)
            ->setFamily($family)
            ->setRole($invitation->getRole())
            ->setStatus(MembershipStatus::Active);

        // Droits granulaires proposés (ignorés pour un parent : edit forcé).
        if (!$invitation->getRole()->forcesEdit()) {
            foreach ($invitation->getProposedRights() as $domain => $level) {
                $rightDomain = RightDomain::tryFrom((string) $domain);
                $rightLevel = RightLevel::tryFrom((string) $level);
                if (null !== $rightDomain && null !== $rightLevel) {
                    $membership->addRight(new Right($rightDomain, $rightLevel));
                }
            }
        }

        $this->em->persist($membership);
        $invitation->setStatus(InvitationStatus::Accepted);
        $this->audit->log(AuditAction::InvitationAccepted, $user, $family, ip: $ip); // flush

        return FamilyResource::fromMembership($membership);
    }
}
