<?php

declare(strict_types=1);

namespace App\State\Member;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Member\MemberResource;
use App\Dto\Member\UpdateMembershipRequest;
use App\Entity\Membership;
use App\Enum\AuditAction;
use App\Enum\MembershipRole;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Exception\AdminRequiresParentRoleException;
use App\Exception\MembershipNotFoundException;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use App\Security\Membership\LastAdminGuard;
use App\Security\Membership\MembershipWriter;
use App\State\Concern\ReadsProvidedKeys;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-MBR-2/5 — modifie le rôle, le flag admin, les droits et la couleur d'un membre.
 *
 * Réservé `family_settings:edit` (admin), sauf le changement de **sa propre couleur** (self).
 * Applique, dans l'ordre (§3.7 → §3.2) : invariant `is_admin ⇒ role=parent`, puis protection du
 * dernier admin (verrou), puis normalisation des droits. Tout dans une transaction.
 *
 * @implements ProcessorInterface<UpdateMembershipRequest, MemberResource>
 */
final class UpdateMembershipProcessor implements ProcessorInterface
{
    use ReadsProvidedKeys;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FamilyScope $scope,
        private readonly MembershipRepository $memberships,
        private readonly MembershipWriter $writer,
        private readonly LastAdminGuard $lastAdmin,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MemberResource
    {
        \assert($data instanceof UpdateMembershipRequest);

        $ctx = $this->scope->require();
        $membership = $this->loadInScope($uriVariables['id'] ?? null, $ctx->family);
        $provided = $this->providedKeys();

        $isSelf = $membership->getUser()->getId()->equals($ctx->user()->getId());
        $touchesStructure = (bool) array_intersect($provided, ['role', 'isAdmin', 'rights']);

        // Couleur : éditable par le membre lui-même sans droit family_settings.
        if (!$touchesStructure && \in_array('displayColor', $provided, true) && $isSelf) {
            $membership->setDisplayColor($data->displayColor);
            $this->em->flush();

            return MemberResource::fromMembership($membership, self: true);
        }

        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $before = $this->snapshot($membership);
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        // Mutation + audit (avant/après) committés atomiquement.
        $this->em->wrapInTransaction(function () use ($membership, $data, $provided, $before, $ip, $ctx): void {
            [$role, $isAdmin] = $this->resolveRoleAndAdmin($membership, $data, $provided);

            // §3.2 : si l'opération retire l'admin d'un membre actuellement admin → protection du dernier admin.
            if ($membership->isAdmin() && !$isAdmin) {
                $this->lastAdmin->assertNotLastAdmin($membership);
            }

            $rights = \in_array('rights', $provided, true) && null !== $data->rights
                ? $data->rights
                : $this->currentRights($membership);

            $this->writer->apply($membership, $role, $isAdmin, $rights);

            if (\in_array('displayColor', $provided, true)) {
                $membership->setDisplayColor($data->displayColor);
            }

            $this->audit->persist(
                AuditAction::MembershipRightsUpdated, $ctx->user(), $ctx->family, ip: $ip,
                metadata: ['membership' => (string) $membership->getId(), 'before' => $before, 'after' => $this->snapshot($membership)],
                entityType: 'Membership', entityId: (string) $membership->getId(),
            );
        });

        return MemberResource::fromMembership($membership, self: $isSelf);
    }

    /**
     * Résout (rôle, is_admin) cibles en appliquant la sémantique §3.7 :
     * - `{isAdmin:true}` explicite sur un rôle non-parent → 422 ;
     * - un rôle non-parent ne peut être admin (clear de fait → demote_role §3.2).
     *
     * @param array<int, string> $provided
     *
     * @return array{0: MembershipRole, 1: bool}
     */
    private function resolveRoleAndAdmin(Membership $membership, UpdateMembershipRequest $data, array $provided): array
    {
        $role = \in_array('role', $provided, true) && null !== $data->role
            ? MembershipRole::from($data->role)
            : $membership->getRole();

        if (\in_array('isAdmin', $provided, true) && null !== $data->isAdmin) {
            $isAdmin = $data->isAdmin;
            if ($isAdmin && MembershipRole::Parent !== $role) {
                throw new AdminRequiresParentRoleException();
            }
        } else {
            $isAdmin = $membership->isAdmin();
        }

        if (MembershipRole::Parent !== $role) {
            $isAdmin = false; // un non-parent n'est jamais admin (de facto)
        }

        return [$role, $isAdmin];
    }

    private function loadInScope(mixed $id, \App\Entity\Family $family): Membership
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new MembershipNotFoundException();
        }
        $membership = $this->memberships->find(Uuid::fromString($id));
        if (!$membership instanceof Membership || !$membership->getFamily()->getId()->equals($family->getId())) {
            throw new MembershipNotFoundException();
        }

        return $membership;
    }

    /** @return array<string, string> domaine → niveau matérialisé courant */
    private function currentRights(Membership $membership): array
    {
        $map = [];
        foreach ($membership->getRights() as $right) {
            $map[$right->getDomain()->value] = $right->getLevel()->value;
        }

        return $map;
    }

    /** @return array{role: string, isAdmin: bool, rights: array<string, string>} */
    private function snapshot(Membership $membership): array
    {
        $rights = [];
        foreach (RightDomain::cases() as $domain) {
            $rights[$domain->value] = $membership->effectiveLevel($domain)->value;
        }

        return ['role' => $membership->getRole()->value, 'isAdmin' => $membership->isAdmin(), 'rights' => $rights];
    }

    protected function requestStack(): RequestStack
    {
        return $this->requestStack;
    }
}
