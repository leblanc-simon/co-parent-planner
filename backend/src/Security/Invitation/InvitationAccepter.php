<?php

declare(strict_types=1);

namespace App\Security\Invitation;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Enum\MembershipStatus;
use App\Exception\AlreadyMemberException;
use App\Exception\InvalidInvitationException;
use App\Exception\InvitationExpiredException;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\TokenHasher;
use App\Security\Membership\MembershipWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\Clock;

/**
 * Cœur partagé de l'acceptation d'invitation (§3.3 C) : chargement verrouillé + validation, puis
 * création du Membership avec transition `pending→accepted` et audits atomiques. Réutilisé par les
 * trois chemins d'acceptation — utilisateur déjà connecté ({@see \App\State\Invitation\AcceptInvitationProcessor}),
 * création de compte ({@see \App\State\Invitation\RegisterAndAcceptInvitationProcessor}) et connexion
 * ({@see \App\State\Invitation\LoginAndAcceptInvitationProcessor}) — pour garantir un comportement
 * de sécurité identique (usage unique, expiry, invariants de droits) quel que soit le point d'entrée.
 *
 * Toutes les méthodes s'appellent **dans une transaction** (`wrapInTransaction`) : le verrou pessimiste
 * sur l'invitation sérialise les acceptations concurrentes et les audits sont committés atomiquement.
 */
final class InvitationAccepter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationRepository $invitations,
        private readonly MembershipRepository $memberships,
        private readonly MembershipWriter $writer,
        private readonly TokenHasher $hasher,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Charge l'invitation `pending` et non expirée correspondant au token, sous verrou pessimiste
     * (SELECT … FOR UPDATE). **À appeler dans une transaction.**.
     *
     * @throws InvalidInvitationException introuvable / révoquée / déjà acceptée (400 non énumérant)
     * @throws InvitationExpiredException échéance dépassée (410)
     */
    public function loadPendingForUpdate(string $token): Invitation
    {
        $invitation = $this->invitations->findOneByTokenHashForUpdate($this->hasher->hash($token));

        // Introuvable / révoquée / déjà acceptée → réponse non énumérante (400 générique).
        if (null === $invitation || InvitationStatus::Pending !== $invitation->getStatus()) {
            throw new InvalidInvitationException();
        }
        // Le marquage `expired` est assuré par la purge planifiée (§3.3.E) — ici on refuse seulement.
        if ($invitation->getExpiresAt() <= Clock::get()->now()) {
            throw new InvitationExpiredException();
        }

        return $invitation;
    }

    /**
     * Crée le Membership (rôle + droits normalisés ; admin honoré uniquement pour un parent), passe
     * l'invitation à `accepted` et journalise. **À appeler dans une transaction.**.
     *
     * @throws AlreadyMemberException si l'utilisateur est déjà membre de la famille
     */
    public function accept(Invitation $invitation, User $user, ?string $ip): Membership
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

        // Audits journalisés DANS la transaction (atomiques avec l'écriture) : la consommation de
        // l'invitation (action sensible §3.3) ET la création du membership sont traçables.
        $this->audit->persist(
            AuditAction::InvitationAccepted, $user, $family, ip: $ip,
            entityType: 'Invitation', entityId: (string) $invitation->getId(),
        );
        $this->audit->persist(
            AuditAction::MembershipCreated, $user, $family, ip: $ip, metadata: ['via' => 'invitation'],
            entityType: 'Membership', entityId: (string) $membership->getId(),
        );

        return $membership;
    }
}
