<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Dto\Exchange\ExchangeResource;
use App\Dto\Exchange\MessageView;
use App\Dto\Exchange\OptionView;
use App\Entity\Exchange;
use App\Entity\Membership;
use App\Repository\ExchangeOptionRepository;
use App\Repository\MessageRepository;
use App\Repository\OptionVoteRepository;

/**
 * Assemble les `ExchangeResource` à partir des entités (Lot 6 §6). Centralise le shaping lecture :
 * décomptes de votes, options ordonnées, fil de messages (déchiffré), marqueur « en retard ». La
 * liste précalcule tally + stats en **une** requête chacune (évite le N+1, §3.5/§3.6).
 */
final class ExchangeAssembler
{
    public function __construct(
        private readonly ExchangeOptionRepository $options,
        private readonly MessageRepository $messages,
        private readonly OptionVoteRepository $votes,
        private readonly OverdueChecker $overdue,
        private readonly DecisionEngine $decisions,
    ) {
    }

    /**
     * @param list<Exchange> $exchanges
     *
     * @return list<ExchangeResource>
     */
    public function list(array $exchanges, \DateTimeZone $tz): array
    {
        $tally = $this->votes->tallyFor($exchanges);
        $stats = $this->messages->statsFor($exchanges);

        return array_map(fn (Exchange $e): ExchangeResource => $this->summary($e, $tz, $tally, $stats), $exchanges);
    }

    /**
     * Résumé (sans le fil), à partir des décomptes/stats précalculés.
     *
     * @param array<string, int>                                          $tally
     * @param array<string, array{count: int, last: ?\DateTimeImmutable}> $stats
     */
    public function summary(Exchange $exchange, \DateTimeZone $tz, array $tally, array $stats): ExchangeResource
    {
        $res = ExchangeResource::scalarFrom($exchange, $this->overdue->isOverdue($exchange, $tz));
        $res->options = $this->optionViews($exchange, $tally);
        $stat = $stats[(string) $exchange->getId()] ?? null;
        $res->messageCount = $stat['count'] ?? 0;
        $res->lastMessageAt = ($stat['last'] ?? null)?->setTimezone($tz)->format(\DateTimeInterface::ATOM);

        return $res;
    }

    /**
     * Détail complet (options + décomptes + fil déchiffré).
     *
     * [$viewer] renseigné : la ressource porte en plus l'**état du tour de vote** du point de vue de
     * ce membre (X1). Facultatif, parce que la liste n'en a pas besoin — et qu'un appelant qui
     * l'oublie doit obtenir un détail sans état personnel, jamais l'état de quelqu'un d'autre.
     */
    public function detail(Exchange $exchange, \DateTimeZone $tz, ?Membership $viewer = null): ExchangeResource
    {
        $tally = $this->votes->tallyFor([$exchange]);
        $myOptionId = $this->myVotedOptionId($exchange, $viewer);
        $res = ExchangeResource::scalarFrom($exchange, $this->overdue->isOverdue($exchange, $tz));
        $res->options = $this->optionViews($exchange, $tally, $myOptionId);
        $res->myVotedOptionId = $myOptionId;
        [$res->awaitingVoters, $res->myVoteMaySettle] = $this->voteTurn($exchange, $viewer);

        $messages = $this->messages->findForExchange($exchange);
        $res->messages = array_map(static fn ($m): MessageView => MessageView::fromEntity($m, $tz), $messages);
        $res->messageCount = \count($messages);
        $last = [] !== $messages ? end($messages) : null;
        $res->lastMessageAt = false !== $last && null !== $last ? $last->getSentAt()->setTimezone($tz)->format(\DateTimeInterface::ATOM) : null;

        return $res;
    }

    /**
     * @param array<string, int> $tally
     *
     * @return list<OptionView>
     */
    private function optionViews(Exchange $exchange, array $tally, ?string $myOptionId = null): array
    {
        if (!$exchange->isDecision()) {
            return [];
        }
        $decidedId = null !== $exchange->getDecidedOption() ? (string) $exchange->getDecidedOption()->getId() : null;

        return array_map(
            static fn ($o): OptionView => OptionView::fromEntity(
                $o,
                $tally[(string) $o->getId()] ?? 0,
                (string) $o->getId() === $decidedId,
                null !== $myOptionId && (string) $o->getId() === $myOptionId,
            ),
            $this->options->findForExchange($exchange),
        );
    }

    /** Option validée par [$viewer], ou `null` s'il n'a pas voté (ou n'est pas renseigné). */
    private function myVotedOptionId(Exchange $exchange, ?Membership $viewer): ?string
    {
        if (null === $viewer || !$exchange->isDecision()) {
            return null;
        }
        $vote = $this->votes->findOneForMember($exchange, $viewer);

        return null !== $vote ? (string) $vote->getOption()->getId() : null;
    }

    /**
     * Qui manque encore à l'appel, et mon vote peut-il trancher.
     *
     * La règle vit ici parce qu'elle vit déjà là — dans `DecisionEngine` : la redériver côté web et
     * côté mobile ferait trois définitions du même consensus, dont deux dériveraient.
     *
     * @return array{0: list<string>, 1: bool}
     */
    private function voteTurn(Exchange $exchange, ?Membership $viewer): array
    {
        if (null === $viewer || !$exchange->isDecision() || !$exchange->isOpen()) {
            return [[], false];
        }

        $voted = [];
        foreach ($this->votes->findForExchange($exchange) as $vote) {
            $voted[(string) $vote->getMembership()->getId()] = true;
        }

        $awaiting = [];
        $meMissing = false;
        foreach ($this->decisions->electorate($exchange->getFamily()) as $elector) {
            $id = (string) $elector->getId();
            if (isset($voted[$id])) {
                continue;
            }
            if ($id === (string) $viewer->getId()) {
                $meMissing = true;
                continue;
            }
            $user = $elector->getUser();
            $awaiting[] = $user->getFullName() ?: $user->getEmail();
        }

        // « Peut » trancher : tous les autres ont voté, mais une égalité laisserait l'échange
        // ouvert (§3.3) — l'annoncer comme certain serait mentir une fois sur deux.
        return [$awaiting, $meMissing && [] === $awaiting];
    }
}
