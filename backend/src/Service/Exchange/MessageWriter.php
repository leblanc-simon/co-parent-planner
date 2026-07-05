<?php

declare(strict_types=1);

namespace App\Service\Exchange;

use App\Entity\Exchange;
use App\Entity\Membership;
use App\Entity\Message;
use App\Exception\ExchangeInputException;
use Psr\Clock\ClockInterface;

/**
 * Valide et construit un `Message` (Lot 6 §3.2). Texte non vide (après trim), longueur ≤ MAX ;
 * `sent_at` = horloge serveur (UTC, autorité serveur §4.7). Le chiffrement 🔒 est appliqué par le
 * type Doctrine `EncryptedStringType` à la persistance.
 */
final class MessageWriter
{
    public const int MAX_LENGTH = 5000;

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function build(Exchange $exchange, Membership $author, ?string $rawText): Message
    {
        $text = trim((string) $rawText);
        if ('' === $text) {
            throw new ExchangeInputException('exchange.error.message_required');
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw new ExchangeInputException('exchange.error.message_too_long');
        }

        return (new Message())
            ->setExchange($exchange)
            ->setAuthorMembership($author)
            ->setText($text)
            ->setSentAt($this->clock->now());
    }
}
