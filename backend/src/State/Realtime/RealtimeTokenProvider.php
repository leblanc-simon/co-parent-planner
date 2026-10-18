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
use Symfony\Component\Mercure\Jwt\Grant;
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
        $topics = $this->subscribeTopicsFor($membership);
        $token = $factory->create([] === $topics ? [] : [new Grant([Grant::ACTION_SUBSCRIBE], $topics)]);

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
     * Forme attendue par {@see Grant} : topics exacts sous `exact`, motifs sous `urlpattern`.
     *
     * @return array<string, list<string>>
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
            $topics['exact'][] = \sprintf('/families/%s/notes', $familyId);
        }
        // Garde (Lot 3) : abonnement filtré sur le droit custody ≥ view (US-CUST-5).
        if ($membership->can(RightDomain::Custody, RightLevel::View)) {
            $topics['exact'][] = \sprintf('/families/%s/custody', $familyId);
        }
        // Agenda (Lot 4) : abonnement filtré sur le droit agenda ≥ view (US-AG-8).
        if ($membership->can(RightDomain::Agenda, RightLevel::View)) {
            $topics['exact'][] = \sprintf('/families/%s/agenda', $familyId);
        }
        // Dépenses (Lot 10, v2) : abonnement filtré sur `expenses ≥ view`, ce qui suffit à couvrir les
        // trois conditions du §3.5 — le niveau effectif vaut déjà `none` si le module est désactivé
        // pour la famille ou si le membre est un enfant (cf. Membership::effectiveLevel()).
        if ($membership->can(RightDomain::Expenses, RightLevel::View)) {
            $topics['exact'][] = \sprintf('/families/%s/expenses', $familyId);
        }
        // Échanges (Lot 6) : liste (badge) + détail par échange (URL Pattern, Mercure ≥ 1.0 — les
        // gabarits RFC 6570 n'existent plus), filtré sur exchanges ≥ view (US-EX-2/7). Le clair des
        // messages n'est poussé qu'ici, aux abonnés autorisés.
        if ($membership->can(RightDomain::Exchanges, RightLevel::View)) {
            $topics['exact'][] = \sprintf('/families/%s/exchanges', $familyId);
            $topics['urlpattern'][] = \sprintf('/families/%s/exchanges/:exchangeId', $familyId);
        }

        return $topics;
    }
}
