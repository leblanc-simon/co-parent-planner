<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Dto\Exchange\CreateExchangeRequest;
use App\Dto\Exchange\UpdateExchangeRequest;
use App\Entity\Child;
use App\Entity\Exchange;
use App\Entity\ExchangeOption;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\ExchangeStatus;
use App\Enum\ExchangeType;
use App\Exception\ExchangeConflictException;
use App\Exception\ExchangeInputException;
use App\Repository\ChildRepository;
use App\Repository\ExchangeOptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Valide et construit / met à jour un `Exchange` (Lot 6 §3.1, §3.3, §6). Création : titre non vide,
 * type valide, enfant appartenant à la famille (ou « toute la famille »), échéance date valide,
 * décision ≥ 2 options à libellé non vide. PATCH : édition d'échéance, clôture d'une discussion,
 * forçage d'une décision (`decidedOptionId`), réouverture interdite (§4.5, §6.2).
 */
final class ExchangeWriter
{
    /** Format d'échéance : date pure `Y-m-d`. */
    private const string DATE_FORMAT = 'Y-m-d';

    /* Bornes alignées sur les colonnes (`exchanges.title`, `exchange_options.label`/`sub`) et, pour
     * le corps libre, sur celle des notes. Sans elles, une saisie trop longue traversait toute
     * l'application et remontait en 500 depuis PostgreSQL — « value too long for type character
     * varying(200) » —, exposant au passage les types et longueurs des colonnes (revue 2026-08 C6). */
    private const int MAX_TITLE = 200;
    private const int MAX_BODY = 5000;
    private const int MAX_OPTION_LABEL = 200;
    private const int MAX_OPTION_SUB = 255;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChildRepository $children,
        private readonly ExchangeOptionRepository $options,
    ) {
    }

    public function build(Family $family, Membership $author, CreateExchangeRequest $data): Exchange
    {
        $title = trim((string) $data->title);
        if ('' === $title) {
            throw new ExchangeInputException('exchange.error.title_required');
        }
        if (mb_strlen($title) > self::MAX_TITLE) {
            throw new ExchangeInputException('exchange.error.title_too_long');
        }

        $type = ExchangeType::tryFrom((string) $data->type);
        if (null === $type) {
            throw new ExchangeInputException('exchange.error.type_invalid');
        }

        $body = null !== $data->body ? trim($data->body) : null;
        $body = '' === $body ? null : $body;
        if (null !== $body && mb_strlen($body) > self::MAX_BODY) {
            throw new ExchangeInputException('exchange.error.body_too_long');
        }

        $exchange = (new Exchange())
            ->setFamily($family)
            ->setType($type)
            ->setTitle($title)
            ->setBody($body)
            ->setAuthorMembership($author)
            ->setChild($this->resolveChild($family, $data->childId))
            ->setDeadline($this->parseDeadline($data->deadline))
            ->setStatus(ExchangeStatus::Open);

        $this->em->persist($exchange);

        if (ExchangeType::Decision === $type) {
            $this->buildOptions($exchange, $data->options);
        }

        return $exchange;
    }

    /**
     * Applique un PATCH (clôture / forçage / échéance). Renvoie `true` si l'échange **passe** de
     * `open` à `decided` (utile au fan-out badge et à l'audit).
     */
    public function applyPatch(Exchange $exchange, UpdateExchangeRequest $data): bool
    {
        $wasOpen = $exchange->isOpen();

        // Échéance : chaîne '' = effacer, 'Y-m-d' = fixer, null = inchangé.
        if (null !== $data->deadline) {
            $exchange->setDeadline('' === $data->deadline ? null : $this->parseDeadline($data->deadline));
        }

        $forceOption = null !== $data->decidedOptionId;
        $wantClose = 'decided' === $data->status || $forceOption;

        if ($wantClose) {
            if (!$exchange->isOpen()) {
                throw new ExchangeConflictException('exchange.error.already_decided');
            }
            if ($forceOption) {
                $exchange->setDecidedOption($this->resolveOption($exchange, (string) $data->decidedOptionId));
            } elseif ($exchange->isDecision()) {
                // Clore une décision sans option gagnante n'a pas de sens : exiger decidedOptionId.
                throw new ExchangeInputException('exchange.error.decided_option_required');
            }
            $exchange->setStatus(ExchangeStatus::Decided);
        } elseif (null !== $data->status && 'open' === $data->status && !$exchange->isOpen()) {
            throw new ExchangeConflictException('exchange.error.reopen_forbidden');
        } elseif (null !== $data->status && !\in_array($data->status, ['open', 'decided'], true)) {
            throw new ExchangeInputException('exchange.error.status_invalid');
        }

        return $wasOpen && !$exchange->isOpen();
    }

    /**
     * @param array<int, array{label?: string|null, sub?: string|null, position?: int|null}>|null $rawOptions
     */
    private function buildOptions(Exchange $exchange, ?array $rawOptions): void
    {
        $rawOptions ??= [];
        if (\count($rawOptions) < 2) {
            throw new ExchangeInputException('exchange.error.options_min');
        }

        $position = 0;
        foreach ($rawOptions as $raw) {
            $label = trim((string) ($raw['label'] ?? ''));
            if ('' === $label) {
                throw new ExchangeInputException('exchange.error.option_label_required');
            }
            if (mb_strlen($label) > self::MAX_OPTION_LABEL) {
                throw new ExchangeInputException('exchange.error.option_label_too_long');
            }
            $sub = isset($raw['sub']) ? trim((string) $raw['sub']) : null;
            if (null !== $sub && mb_strlen($sub) > self::MAX_OPTION_SUB) {
                throw new ExchangeInputException('exchange.error.option_sub_too_long');
            }
            $option = (new ExchangeOption())
                ->setExchange($exchange)
                ->setLabel($label)
                ->setSub('' === $sub ? null : $sub)
                ->setPosition($position++);
            $this->em->persist($option);
        }
    }

    private function resolveChild(Family $family, ?string $childId): ?Child
    {
        if (null === $childId || '' === $childId) {
            return null; // toute la famille
        }
        if (!Uuid::isValid($childId)) {
            throw new ExchangeInputException('exchange.error.child_invalid');
        }
        $child = $this->children->find(Uuid::fromString($childId));
        if (!$child instanceof Child || !$child->getFamily()->getId()->equals($family->getId())) {
            throw new ExchangeInputException('exchange.error.child_invalid');
        }

        return $child;
    }

    private function resolveOption(Exchange $exchange, string $optionId): ExchangeOption
    {
        if (!$exchange->isDecision()) {
            throw new ExchangeInputException('exchange.error.not_a_decision');
        }
        if (!Uuid::isValid($optionId)) {
            throw new ExchangeInputException('exchange.error.option_invalid');
        }
        foreach ($this->options->findForExchange($exchange) as $option) {
            if ($option->getId()->equals(Uuid::fromString($optionId))) {
                return $option;
            }
        }

        throw new ExchangeInputException('exchange.error.option_invalid');
    }

    private function parseDeadline(?string $raw): ?\DateTimeImmutable
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $raw);
        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $date || (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ExchangeInputException('exchange.error.deadline_invalid');
        }

        return $date;
    }
}
