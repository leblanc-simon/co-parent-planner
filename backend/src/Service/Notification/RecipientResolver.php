<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\NotificationType;
use App\Enum\RightLevel;
use App\Repository\MembershipRepository;

/**
 * Résout les destinataires d'une notification (Lot 7 §3.2) : memberships **actifs** de la famille
 * filtrés par le **Voter** du domaine (droit ≥ view) — la sécurité prime sur la préférence. La
 * visibilité agenda/échanges est au niveau du domaine (comme aux Lots 4/6), pas par enfant.
 */
final class RecipientResolver
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    /**
     * @return list<Membership>
     */
    public function resolve(Family $family, NotificationType $type): array
    {
        $domain = $type->rightDomain();

        return array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn (Membership $m): bool => $m->can($domain, RightLevel::View),
        ));
    }
}
