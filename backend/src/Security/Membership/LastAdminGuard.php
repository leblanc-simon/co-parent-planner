<?php

declare(strict_types=1);

namespace App\Security\Membership;

use App\Entity\Membership;
use App\Exception\LastAdminProtectedException;
use App\Repository\MembershipRepository;

/**
 * Protection du dernier admin (invariant §3.2) : interdit tout retrait/rétrogradation qui
 * laisserait une famille sans admin actif. Sérialise les opérations concurrentes via un
 * verrou pessimiste sur les admins de la famille — **à appeler dans une transaction**.
 */
final class LastAdminGuard
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    /**
     * @throws LastAdminProtectedException si le membership ciblé est l'unique admin actif
     */
    public function assertNotLastAdmin(Membership $target): void
    {
        // Retirer/rétrograder un non-admin n'affecte jamais le quota d'admins.
        if (!$target->isAdmin()) {
            return;
        }

        if ($this->memberships->lockAndCountActiveAdmins($target->getFamily()) <= 1) {
            throw new LastAdminProtectedException();
        }
    }
}
