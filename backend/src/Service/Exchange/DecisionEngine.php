<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Entity\Exchange;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\ExchangeStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Repository\MembershipRepository;
use App\Repository\OptionVoteRepository;

/**
 * Cycle de vie d'une décision (Lot 6 §3.3). **Électorat** = memberships actifs de la famille avec
 * `exchanges=edit` (parents + beaux-parents habilités ; jamais `view`/`none`/enfants). **Bascule par
 * consensus** : la décision passe `decided` dès que **tous** les électeurs ont voté **et** qu'une
 * option recueille une **majorité stricte** ; en cas d'égalité, elle reste `open` (pas de tie-break).
 */
final class DecisionEngine
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly OptionVoteRepository $votes,
    ) {
    }

    /**
     * Memberships habilités à trancher (§3.3).
     *
     * @return list<Membership>
     */
    public function electorate(Family $family): array
    {
        return array_values(array_filter(
            $this->memberships->findActiveByFamily($family),
            static fn (Membership $m): bool => $m->can(RightDomain::Exchanges, RightLevel::Edit),
        ));
    }

    /**
     * Ré-évalue la règle de bascule après un vote. En cas de consensus, mute l'échange
     * (`status=decided`, `decidedOption`) et renvoie `true` ; sinon laisse `open` et renvoie `false`.
     */
    public function evaluate(Exchange $exchange): bool
    {
        $electorate = $this->electorate($exchange->getFamily());
        $electorateIds = array_map(static fn (Membership $m): string => (string) $m->getId(), $electorate);

        // Ne compter que les votes des électeurs actuels (un membre rétrogradé après vote ne compte plus).
        $relevant = array_filter(
            $this->votes->findForExchange($exchange),
            static fn ($v): bool => \in_array((string) $v->getMembership()->getId(), $electorateIds, true),
        );

        // Tous les électeurs doivent avoir voté.
        if (\count($relevant) < \count($electorate) || [] === $electorate) {
            return false;
        }

        $tally = [];
        foreach ($relevant as $vote) {
            $optionId = (string) $vote->getOption()->getId();
            $tally[$optionId] = ($tally[$optionId] ?? 0) + 1;
        }
        if ([] === $tally) {
            return false;
        }

        $max = max($tally);
        $winners = array_keys($tally, $max, true);
        if (1 !== \count($winners)) {
            return false; // égalité → reste ouvert (§3.3)
        }

        $winnerId = $winners[0];
        foreach ($relevant as $vote) {
            if ((string) $vote->getOption()->getId() === $winnerId) {
                $exchange->setStatus(ExchangeStatus::Decided)->setDecidedOption($vote->getOption());

                return true;
            }
        }

        return false;
    }
}
