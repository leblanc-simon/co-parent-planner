<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Dto\Exchange\ExchangeResource;
use App\Dto\Exchange\MessageView;
use App\Dto\Exchange\OptionView;
use App\Entity\Exchange;
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

    /** Détail complet (options + décomptes + fil déchiffré). */
    public function detail(Exchange $exchange, \DateTimeZone $tz): ExchangeResource
    {
        $tally = $this->votes->tallyFor([$exchange]);
        $res = ExchangeResource::scalarFrom($exchange, $this->overdue->isOverdue($exchange, $tz));
        $res->options = $this->optionViews($exchange, $tally);

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
    private function optionViews(Exchange $exchange, array $tally): array
    {
        if (!$exchange->isDecision()) {
            return [];
        }
        $decidedId = null !== $exchange->getDecidedOption() ? (string) $exchange->getDecidedOption()->getId() : null;

        return array_map(
            static fn ($o): OptionView => OptionView::fromEntity($o, $tally[(string) $o->getId()] ?? 0, (string) $o->getId() === $decidedId),
            $this->options->findForExchange($exchange),
        );
    }
}
