<?php

declare(strict_types=1);

namespace App\Rgpd;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserStatus;
use App\Repository\DeviceTokenRepository;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\NotificationPrefRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Auth\RefreshTokenManager;
use App\Security\Encryption\BlindIndexHasher;
use App\Security\Membership\LastAdminGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Anonymiseur (Lot 8 §3.3) : neutralise **irréversiblement** les données personnelles d'un compte
 * tout en **préservant l'intégrité des fils** (messages/notes conservés, auteur ré-attribué au marqueur
 * « Membre supprimé » via le `ON DELETE SET NULL` de leurs FK auteur — même mécanisme que le retrait
 * de membre du Lot 1).
 *
 * Invariants respectés :
 *  - dernier admin protégé (CLAUDE.md) : abandon (409) si le sujet est l'unique admin actif d'une famille ;
 *  - irréversibilité : les champs 🔒 (`fullName`, `phone`) sont écrasés (null), l'e-mail pseudonymisé,
 *    le hash de mot de passe rendu inutilisable — aucune valeur claire d'origine n'est conservée ;
 *  - idempotence : un compte déjà `deleted` est un no-op.
 *
 * Déclencheurs : `DELETE /api/me` (sujet = demandeur) et la purge des comptes inactifs (sujet ≠ demandeur).
 */
final class Anonymizer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipRepository $memberships,
        private readonly LastAdminGuard $lastAdmin,
        private readonly RefreshTokenManager $refreshTokens,
        private readonly NotificationPrefRepository $notificationPrefs,
        private readonly InvitationRepository $invitations,
        private readonly DeviceTokenRepository $deviceTokens,
        private readonly BlindIndexHasher $blindIndex,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Plan (dry-run) : ce qui SERAIT neutralisé, sans rien écrire.
     */
    public function plan(User $user): AnonymizationResult
    {
        if (UserStatus::Deleted === $user->getStatus()) {
            return new AnonymizationResult(true, [], 0, 0);
        }

        $memberships = $this->em->getRepository(Membership::class)->findBy(['user' => $user]);

        return new AnonymizationResult(
            false,
            $this->neutralizableFields($user),
            \count($memberships),
            $this->notificationPrefs->countForUser($user),
        );
    }

    /**
     * Le sujet est-il l'unique admin actif d'au moins une famille ? (garde-fou purge : exclusion + écart.)
     * Lecture non verrouillante — la garantie stricte reste le verrou pessimiste de {@see anonymize()}.
     */
    public function isBlockedByLastAdmin(User $user): bool
    {
        foreach ($this->em->getRepository(Membership::class)->findBy(['user' => $user]) as $membership) {
            if ($membership->isActive() && $membership->isAdmin()
                && $this->memberships->countActiveAdmins($membership->getFamily()) <= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Anonymisation effective (live) dans une transaction. Idempotent.
     *
     * @throws \App\Exception\LastAdminProtectedException si le sujet est l'unique admin actif d'une famille
     */
    public function anonymize(User $user, ?User $requestedBy, ?string $ip): AnonymizationResult
    {
        if (UserStatus::Deleted === $user->getStatus()) {
            return new AnonymizationResult(true, [], 0, 0);
        }

        return $this->em->wrapInTransaction(function () use ($user, $requestedBy, $ip): AnonymizationResult {
            $memberships = $this->em->getRepository(Membership::class)->findBy(['user' => $user]);

            // Garde-fou dernier admin AVANT toute écriture (verrou pessimiste, sérialise la concurrence).
            foreach ($memberships as $membership) {
                if ($membership->isActive() && $membership->isAdmin()) {
                    $this->lastAdmin->assertNotLastAdmin($membership);
                }
            }

            // Retrait des memberships → SET NULL sur les auteurs (fils préservés, « Membre supprimé »).
            $removed = 0;
            foreach ($memberships as $membership) {
                $this->em->remove($membership);
                ++$removed;
            }

            $prefsRemoved = $this->notificationPrefs->deleteForUser($user);

            // Appareils push (Lot 11 §4.8) : identifiants techniques sans valeur pour la cohérence
            // des fils → supprimés, pas anonymisés. Plus aucun push ne part vers ce compte.
            $devicesRemoved = $this->deviceTokens->deleteForUser($user);

            // Purge des invitations adressées au sujet AVANT de perdre son e-mail : `Invitation.email`
            // est chiffré réversiblement (clé applicative) → sans cela, l'effacement serait réversible.
            $this->invitations->deleteByEmailHash($this->blindIndex->hash($user->getEmail()));

            // Neutralisation irréversible des données personnelles du sujet.
            $fields = $this->neutralizableFields($user);
            $user->setFullName(null);
            $user->setPhone(null);
            $user->setEmail(\sprintf('anonymized-%s@anonymized.invalid', Uuid::v7()->toRfc4122()));
            $user->setPasswordHash(bin2hex(random_bytes(32))); // hash inutilisable (aucune connexion)
            $user->setAvatarUrl(null);
            $user->setQuietHoursStart(null);
            $user->setQuietHoursEnd(null);
            $user->setStatus(UserStatus::Deleted);

            // Révocation des sessions (refresh tokens) — réutilisation impossible (§4.8).
            $this->refreshTokens->revokeAllForUser($user);

            $this->audit->persist(
                AuditAction::RgpdAnonymized,
                $requestedBy,
                ip: $ip,
                metadata: [
                    'subject' => (string) $user->getId(),
                    'memberships_removed' => $removed,
                    'notification_prefs_removed' => $prefsRemoved,
                    'devices_removed' => $devicesRemoved,
                ],
                entityType: 'User',
                entityId: (string) $user->getId(),
            );

            return new AnonymizationResult(false, $fields, $removed, $prefsRemoved);
        });
    }

    /** @return list<string> */
    private function neutralizableFields(User $user): array
    {
        $fields = [];
        if (null !== $user->getFullName()) {
            $fields[] = 'fullName';
        }
        if (null !== $user->getPhone()) {
            $fields[] = 'phone';
        }
        $fields[] = 'email';

        return $fields;
    }
}
