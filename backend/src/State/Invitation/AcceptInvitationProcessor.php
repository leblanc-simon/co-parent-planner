<?php

declare(strict_types=1);

namespace App\State\Invitation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Family\FamilyResource;
use App\Dto\Invitation\AcceptInvitationRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Exception\InvitationEmailMismatchException;
use App\Security\Invitation\InvitationAccepter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-INV-3 / US-ONB-1b (§3.3 C) — accepte une invitation pour un utilisateur **déjà connecté** :
 * l'e-mail du compte doit correspondre à l'invité (nominatif, sc.5). Le cœur de l'acceptation (usage
 * unique, expiry, création du Membership, audits) est mutualisé dans {@see InvitationAccepter}, partagé
 * avec les chemins création-de-compte et connexion du flux d'invitation simplifié.
 *
 * @implements ProcessorInterface<AcceptInvitationRequest, FamilyResource>
 */
final class AcceptInvitationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly InvitationAccepter $accepter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyResource
    {
        \assert($data instanceof AcceptInvitationRequest);

        $user = $this->security->getUser();
        \assert($user instanceof User);

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();

        // Tout (membership + transition pending→accepted + audits) est committé atomiquement.
        $membership = $this->em->wrapInTransaction(function () use ($data, $user, $ip): Membership {
            $invitation = $this->accepter->loadPendingForUpdate($data->token);

            // Nominative : l'e-mail du compte connecté doit correspondre (sc.5).
            if (mb_strtolower(trim($invitation->getEmail())) !== $user->getEmail()) {
                throw new InvitationEmailMismatchException();
            }

            return $this->accepter->accept($invitation, $user, $ip);
        });

        return FamilyResource::fromMembership($membership);
    }
}
