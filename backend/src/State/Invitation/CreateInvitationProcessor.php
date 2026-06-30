<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Invitation\CreateInvitationRequest;
use App\Dto\Invitation\InvitationResource;
use App\Entity\Invitation;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\AdminRequiresParentRoleException;
use App\Exception\AlreadyMemberException;
use App\Message\SendInvitationEmail;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\TokenHasher;
use App\Security\Encryption\BlindIndexHasher;
use App\Security\Encryption\FieldEncryptor;
use App\Security\FamilyScope;
use App\Security\Membership\RightsNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US-INV-1 (§3.3 A) — crée une invitation : e-mail 🔒, token haché + expirant, droits normalisés
 * selon le rôle, e-mail d'invitation dispatché en asynchrone. Réservé `family_settings:edit`.
 * Régénère (révoque l'ancienne) s'il existe déjà un `pending` pour ce couple (famille, e-mail).
 *
 * @implements ProcessorInterface<CreateInvitationRequest, InvitationResource>
 */
final class CreateInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyScope $scope,
        private readonly InvitationRepository $invitations,
        private readonly MembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly RightsNormalizer $rights,
        private readonly TokenHasher $tokens,
        private readonly BlindIndexHasher $blind,
        private readonly FieldEncryptor $encryptor,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
        private readonly int $invitationTtl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InvitationResource
    {
        \assert($data instanceof CreateInvitationRequest);
        \assert(null !== $data->email && null !== $data->role);

        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $family = $ctx->family;
        $email = mb_strtolower(trim($data->email));
        $emailHash = $this->blind->hash($email);
        $role = MembershipRole::from($data->role);

        // 5bis. Invariant is_admin ⇒ role=parent : rejeté dès la création (jamais à l'acceptation).
        $proposedAdmin = (bool) $data->isAdmin && MembershipRole::Parent === $role;
        if ((bool) $data->isAdmin && MembershipRole::Parent !== $role) {
            throw new AdminRequiresParentRoleException();
        }

        if (null !== $this->memberships->findActiveByEmail($family, $email)) {
            throw new AlreadyMemberException();
        }

        // Régénération : une invitation pending existante est révoquée au profit de la nouvelle.
        $existing = $this->invitations->findPending($family, $emailHash);
        if (null !== $existing) {
            $existing->setStatus(InvitationStatus::Revoked);
        }

        $plainToken = $this->tokens->generate();
        $invitation = (new Invitation())
            ->setFamily($family)
            ->setEmail($email)
            ->setEmailHash($emailHash)
            ->setRole($role)
            ->setProposedRights($this->normalizedRights($role, $data->rights))
            ->setProposedAdmin($proposedAdmin)
            ->setTokenHash($this->tokens->hash($plainToken))
            ->setExpiresAt(Clock::get()->now()->add(new \DateInterval('PT'.$this->invitationTtl.'S')))
            ->setStatus(InvitationStatus::Pending)
            ->setInvitedBy($ctx->user());

        $this->em->persist($invitation);

        $this->audit->log(
            AuditAction::InvitationCreated,
            $ctx->user(),
            $family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['role' => $role->value],
            entityType: 'Invitation',
            entityId: (string) $invitation->getId(),
        ); // flush

        $this->bus->dispatch(new SendInvitationEmail(
            toEmail: $email,
            locale: $this->users->findOneByEmail($email)?->getLocale() ?? 'fr-FR',
            familyName: $family->getName(),
            role: $role->value,
            token: (string) $this->encryptor->encrypt($plainToken),
        ));

        return InvitationResource::fromInvitation($invitation);
    }

    /**
     * @param array<string, string>|null $requested
     *
     * @return array<string, string> domaine → niveau (sans les none), normalisé selon le rôle
     */
    private function normalizedRights(MembershipRole $role, ?array $requested): array
    {
        $map = [];
        foreach ($this->rights->normalize($role, $requested ?? []) as $domain => $level) {
            $map[$domain] = $level->value;
        }

        return $map;
    }
}
