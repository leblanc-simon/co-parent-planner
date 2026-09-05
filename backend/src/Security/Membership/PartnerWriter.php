<?php

declare(strict_types=1);

namespace App\Security\Membership;

use App\Entity\Membership;
use App\Enum\MembershipRole;
use App\Exception\InvalidPartnerException;
use App\Exception\MembershipNotFoundException;
use App\Repository\MembershipRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Association parent ↔ beau-parent (évolution E1, `specs/evolutions-2026-09.md`).
 *
 * La relation est **portée par le beau-parent** et vaut **1 ↔ 1** : un beau-parent accompagne au
 * plus un parent, un parent a au plus un conjoint dans la famille. Elle est purement déclarative —
 * elle n'accorde aucun droit et ne touche pas au moteur de garde ; ce que l'un et l'autre peuvent
 * voir reste décidé par les Voters.
 *
 * L'écriture accepte les deux sens de saisie : désigner le parent depuis la ligne du beau-parent,
 * **ou** désigner le beau-parent depuis la ligne du parent. Sans cela, l'écran devrait savoir de
 * quel côté il se trouve, et la moitié des admins s'y tromperait.
 */
final class PartnerWriter
{
    public function __construct(private readonly MembershipRepository $memberships)
    {
    }

    /**
     * Applique l'association demandée sur [$membership], ou la retire si [$partnerId] est `null`.
     */
    public function apply(Membership $membership, ?string $partnerId): void
    {
        if (null === $partnerId) {
            $this->detach($membership);

            return;
        }

        $partner = $this->load($partnerId);
        if (!$partner->getFamily()->getId()->equals($membership->getFamily()->getId())) {
            throw InvalidPartnerException::notInFamily();
        }
        if ($partner->getId()->equals($membership->getId())) {
            throw InvalidPartnerException::itself();
        }

        // Le couple relie un parent et un beau-parent ; deux parents sont les deux coparents, pas
        // un couple, et deux beaux-parents ne se rattachent à aucun foyer.
        [$stepParent, $parent] = match (true) {
            MembershipRole::StepParent === $membership->getRole() && MembershipRole::Parent === $partner->getRole() => [$membership, $partner],
            MembershipRole::Parent === $membership->getRole() && MembershipRole::StepParent === $partner->getRole() => [$partner, $membership],
            default => throw InvalidPartnerException::roles(),
        };

        // 1 ↔ 1 : la contrainte d'unicité de la base l'impose déjà, mais un 422 explicite vaut mieux
        // qu'une violation d'index remontée en 500.
        $taken = $this->memberships->findOneBy(['partnerMembership' => $parent]);
        if ($taken instanceof Membership && !$taken->getId()->equals($stepParent->getId())) {
            throw InvalidPartnerException::alreadyTaken();
        }

        // Le beau-parent ne peut accompagner qu'un parent : sa précédente association tombe.
        $this->detach($stepParent);
        $stepParent->setPartnerMembership($parent);
    }

    /**
     * Retire l'association de [$membership], quel que soit le côté depuis lequel on la retire :
     * un parent qui « n'a plus de conjoint » doit effacer la ligne portée par le beau-parent.
     */
    public function detach(Membership $membership): void
    {
        $membership->setPartnerMembership(null);
        $holder = $this->memberships->findOneBy(['partnerMembership' => $membership]);
        if ($holder instanceof Membership) {
            $holder->setPartnerMembership(null);
        }
    }

    private function load(string $id): Membership
    {
        if (!Uuid::isValid($id)) {
            throw new MembershipNotFoundException();
        }
        $partner = $this->memberships->find(Uuid::fromString($id));
        if (!$partner instanceof Membership) {
            throw new MembershipNotFoundException();
        }

        return $partner;
    }
}
