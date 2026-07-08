<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Exchange\DecideRequest;
use App\Dto\Exchange\ExchangeResource;
use App\Entity\Exchange;
use App\Entity\ExchangeOption;
use App\Entity\OptionVote;
use App\Enum\AuditAction;
use App\Enum\NotificationType;
use App\Exception\ExchangeConflictException;
use App\Exception\ExchangeInputException;
use App\Repository\ExchangeOptionRepository;
use App\Repository\OptionVoteRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\DecisionEngine;
use App\Service\Exchange\ExchangeAssembler;
use App\Service\Exchange\ExchangePublisher;
use App\Service\Notification\NotificationEmitter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

/**
 * US-EX-3 — choisit + valide une option (exchanges:edit). Le vote est **upserté** (un vote actif par
 * membre) ; la règle de bascule (§3.3) est ré-évaluée sous **verrou pessimiste** (`FOR UPDATE`) qui
 * sérialise les `decide` concurrents (le 2ᵉ voit `decided` → 409). Fan-out détail (+ liste si
 * bascule). Émet `ExchangeDecided` sur bascule.
 *
 * @implements ProcessorInterface<DecideRequest, ExchangeResource>
 */
final class DecideProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeAccess $access,
        private readonly ExchangeOptionRepository $options,
        private readonly OptionVoteRepository $votes,
        private readonly DecisionEngine $engine,
        private readonly ExchangeAssembler $assembler,
        private readonly ExchangePublisher $publisher,
        private readonly NotificationEmitter $notifier,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ExchangeResource
    {
        \assert($data instanceof DecideRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $exchange = $this->access->loadInFamily($uriVariables['id'] ?? null, $ctx->family);

        $decided = $this->em->wrapInTransaction(function () use ($exchange, $ctx, $data): bool {
            // Verrou pessimiste + relecture : sérialise les decide concurrents (§3.3, §4.2).
            $this->em->lock($exchange, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($exchange);

            if (!$exchange->isDecision()) {
                throw new ExchangeInputException('exchange.error.not_a_decision');
            }
            if (!$exchange->isOpen()) {
                throw new ExchangeConflictException('exchange.error.already_decided');
            }

            $option = $this->resolveOption($exchange, $data->optionId);

            // Upsert du vote : un seul vote actif par membre et par décision (§5.2).
            $vote = $this->votes->findOneForMember($exchange, $ctx->membership);
            if ($vote instanceof OptionVote) {
                $vote->setOption($option);
            } else {
                $vote = (new OptionVote())
                    ->setExchange($exchange)
                    ->setOption($option)
                    ->setMembership($ctx->membership);
                $this->em->persist($vote);
            }
            $this->em->flush(); // le vote doit être visible du moteur de bascule

            $basculed = $this->engine->evaluate($exchange);
            if ($basculed) {
                $this->audit->persist(
                    AuditAction::ExchangeDecided,
                    $ctx->user(),
                    $ctx->family,
                    ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
                    entityType: 'Exchange',
                    entityId: (string) $exchange->getId(),
                );
            }

            return $basculed;
        });

        // Fan-out post-commit : détail toujours ; liste (badge) uniquement si la décision a basculé.
        $this->publisher->publishExchange($exchange);
        if ($decided) {
            $this->publisher->publishList($ctx->family);
            // Notification e-mail (Lot 7) : décision tranchée → participants habilités (hors auteur).
            $this->notifier->emit(NotificationType::ExchangeDecided, $ctx->family, $ctx->membership, (string) $exchange->getId(), [
                'exchangeId' => (string) $exchange->getId(),
                'title' => $exchange->getTitle(),
            ]);
        }

        return $this->assembler->detail($exchange, $tz);
    }

    private function resolveOption(Exchange $exchange, ?string $optionId): ExchangeOption
    {
        if (!\is_string($optionId) || !Uuid::isValid($optionId)) {
            throw new ExchangeInputException('exchange.error.option_invalid');
        }
        foreach ($this->options->findForExchange($exchange) as $option) {
            if ($option->getId()->equals(Uuid::fromString($optionId))) {
                return $option;
            }
        }

        throw new ExchangeInputException('exchange.error.option_invalid');
    }
}
