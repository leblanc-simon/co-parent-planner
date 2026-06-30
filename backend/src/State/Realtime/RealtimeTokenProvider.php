<?php

declare(strict_types=1);

namespace App\State\Realtime;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\RealtimeToken;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\MembershipRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Émet un JWT Mercure scopé par famille active (§3.12).
 *
 * Le serveur est seule autorité sur les claims `subscribe`. Au Lot 0, aucun topic métier
 * n'existe : la liste est **toujours vide**, mais le mécanisme de scoping par famille et de
 * recalcul à la bascule est posé (les topics arrivent aux lots 2/4/6).
 *
 * @implements ProviderInterface<RealtimeToken>
 */
final class RealtimeTokenProvider implements ProviderInterface
{
    public const string HEADER = 'X-Family-Id';

    public function __construct(
        private readonly Security $security,
        private readonly MembershipRepository $memberships,
        private readonly HubInterface $hub,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RealtimeToken
    {
        $user = $this->security->getUser();
        \assert($user instanceof User); // firewall : endpoint authentifié

        $membership = $this->resolveActiveMembership($user);

        $factory = $this->hub->getFactory();
        \assert(null !== $factory, 'La fabrique de jetons Mercure doit être configurée (MERCURE_JWT_SECRET).');

        // subscribe scopé à la famille active ; publish vide (le client ne publie jamais).
        $token = $factory->create($this->subscribeTopicsFor($membership), []);

        return new RealtimeToken($token, $this->hub->getPublicUrl());
    }

    /**
     * Membership actif correspondant à l'en-tête X-Family-Id, s'il est fourni et valide.
     * Une famille absente / mal formée / non autorisée donne simplement des claims vides (pas d'erreur).
     */
    private function resolveActiveMembership(User $user): ?Membership
    {
        $raw = $this->requestStack->getCurrentRequest()?->headers->get(self::HEADER);
        if (null === $raw || '' === $raw || !Uuid::isValid($raw)) {
            return null;
        }

        return $this->memberships->findActive($user, Uuid::fromString($raw));
    }

    /**
     * Topics autorisés en lecture pour le membre dans cette famille.
     * Au Lot 0 : toujours vide (aucun topic métier). Étendu aux lots 2/4/6 selon les Voters.
     *
     * @return list<string>
     */
    private function subscribeTopicsFor(?Membership $membership): array
    {
        if (null === $membership) {
            return [];
        }

        $topics = [];
        $familyId = $membership->getFamily()->getId();
        // Notes (Lot 2) : abonnement filtré sur le droit children_notes ≥ view (US-CH-3).
        if ($membership->can(RightDomain::ChildrenNotes, RightLevel::View)) {
            $topics[] = \sprintf('/families/%s/notes', $familyId);
        }
        // Garde (Lot 3) : abonnement filtré sur le droit custody ≥ view (US-CUST-5).
        if ($membership->can(RightDomain::Custody, RightLevel::View)) {
            $topics[] = \sprintf('/families/%s/custody', $familyId);
        }

        return $topics;
    }
}
