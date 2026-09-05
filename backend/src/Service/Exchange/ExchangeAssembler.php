<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Dto\Exchange\ExchangeResource;
use App\Dto\Exchange\MessageView;
use App\Dto\Exchange\OptionView;
use App\Entity\Exchange;
use App\Entity\Membership;
use App\Repository\ExchangeOptionRepository;
use App\Repository\ExchangeReadRepository;
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
        private readonly ExchangeReadRepository $reads,
        private readonly OverdueChecker $overdue,
        private readonly DecisionEngine $decisions,
    ) {
    }

    /**
     * [$viewer] renseigné : chaque résumé porte son marqueur de non-lu (X5), calculé en **une**
     * requête pour toute la liste.
     *
     * @param list<Exchange> $exchanges
     *
     * @return list<ExchangeResource>
     */
    public function list(array $exchanges, \DateTimeZone $tz, ?Membership $viewer = null): array
    {
        $tally = $this->votes->tallyFor($exchanges);
        $stats = $this->messages->statsFor($exchanges);
        // `null` (et non `[]`) quand l'appelant ne dit pas qui regarde : sans point de vue, il n'y a
        // pas de non-lu — un tableau vide voudrait dire « ce membre n'a jamais rien lu ».
        $reads = null;
        $lastIds = [];
        if (null !== $viewer && [] !== $exchanges) {
            $reads = $this->reads->lastReadByExchange($viewer, $exchanges[0]->getFamily());
            $lastIds = $this->messages->lastIdsFor($exchanges);
        }

        return array_map(fn (Exchange $e): ExchangeResource => $this->summary($e, $tz, $tally, $stats, $reads, $lastIds), $exchanges);
    }

    /**
     * Résumé (sans le fil), à partir des décomptes/stats précalculés.
     *
     * @param array<string, int>                                                    $tally
     * @param array<string, array{count: int, last: ?\DateTimeImmutable}>           $stats
     * @param array<string, array{at: \DateTimeImmutable, messageId: ?string}>|null $reads   lectures de l'appelant, par échange ; `null` = appelant inconnu
     * @param array<string, string>                                                 $lastIds dernier message de chaque échange
     */
    public function summary(Exchange $exchange, \DateTimeZone $tz, array $tally, array $stats, ?array $reads = null, array $lastIds = []): ExchangeResource
    {
        $res = ExchangeResource::scalarFrom($exchange, $this->overdue->isOverdue($exchange, $tz));
        $res->options = $this->optionViews($exchange, $tally);
        $stat = $stats[(string) $exchange->getId()] ?? null;
        $res->messageCount = $stat['count'] ?? 0;
        $last = $stat['last'] ?? null;
        $res->lastMessageAt = $last?->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        $res->hasUnread = null !== $reads
            && $this->isUnread($lastIds[(string) $exchange->getId()] ?? null, $reads[(string) $exchange->getId()] ?? null);

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
        $lastAt = false !== $last && null !== $last ? $last->getSentAt() : null;
        $res->lastMessageAt = $lastAt?->setTimezone($tz)->format(\DateTimeInterface::ATOM);
        // Le détail dit encore « non lu » : c'est le marquage (POST .../read) qui consomme, pas la
        // lecture — même règle que l'aperçu d'invitation, qui ne consomme pas le jeton.
        // `max()` sur les identifiants, et non le dernier du tri d'affichage : la liste, elle,
        // compare à `MAX(id)` — les deux règles doivent désigner le même message.
        $lastId = [] !== $messages ? max(array_map(static fn ($m): string => (string) $m->getId(), $messages)) : null;
        $res->hasUnread = null !== $viewer && $this->isUnread($lastId, $this->readState($viewer, $exchange));

        return $res;
    }

    /**
     * Lecture de [$viewer] sur cet échange, sous la même forme que la carte de la liste.
     *
     * @return array{at: \DateTimeImmutable, messageId: ?string}|null
     */
    private function readState(Membership $viewer, Exchange $exchange): ?array
    {
        $read = $this->reads->findOneFor($viewer, $exchange);
        if (null === $read) {
            return null;
        }

        return [
            'at' => $read->getLastReadAt(),
            'messageId' => null !== $read->getLastReadMessage() ? (string) $read->getLastReadMessage()->getId() : null,
        ];
    }

    /**
     * A-t-il bougé depuis ma dernière lecture ?
     *
     * Comparaison d'**identifiants de messages**, pas de dates : les horodatages du schéma sont à la
     * seconde, et une réponse postée dans la même seconde qu'une lecture passait pour déjà lue.
     * Jamais lu ⇒ non lu — l'auteur, lui, est marqué lecteur au moment où il crée l'échange ou y
     * poste, sans quoi il verrait ses propres écrits en gras.
     *
     * @param array{at: \DateTimeImmutable, messageId: ?string}|null $read
     */
    private function isUnread(?string $lastMessageId, ?array $read): bool
    {
        if (null === $read) {
            return true;
        }

        return $lastMessageId !== $read['messageId'];
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
