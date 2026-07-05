<?php

declare(strict_types=1);

namespace App\State\Exchange;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Exchange\CreateMessageRequest;
use App\Dto\Exchange\MessageResource;
use App\Entity\Exchange;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Security\Exchanges\ExchangeAccess;
use App\Service\Exchange\ExchangePublisher;
use App\Service\Exchange\MessageWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-EX-2 — poste un message dans le fil (exchanges:edit). Texte chiffré 🔒 au repos, renvoyé en
 * clair. Idempotent sur `Idempotency-Key` (§4.2), sérialisé par advisory lock (échange, clé). Fan-out
 * temps réel **détail** après commit (le clair n'est poussé qu'aux abonnés autorisés du topic).
 *
 * @implements ProcessorInterface<CreateMessageRequest, MessageResource>
 */
final class CreateMessageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeAccess $access,
        private readonly MessageWriter $writer,
        private readonly MessageRepository $messages,
        private readonly ExchangePublisher $publisher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        \assert($data instanceof CreateMessageRequest);

        $ctx = $this->access->write();
        $tz = new \DateTimeZone($ctx->family->getTimezone());
        $exchange = $this->access->loadInFamily($uriVariables['exchangeId'] ?? null, $ctx->family);

        $rawKey = $this->requestStack->getCurrentRequest()?->headers->get('Idempotency-Key');
        $key = \is_string($rawKey) && '' !== $rawKey ? $rawKey : null;

        $message = null !== $key
            ? $this->createIdempotent($exchange, $ctx->membership, $data, $key)
            : $this->createSimple($exchange, $ctx->membership, $data);

        $this->publisher->publishExchange($exchange);

        return MessageResource::fromEntity($message, $tz);
    }

    private function createSimple(Exchange $exchange, \App\Entity\Membership $author, CreateMessageRequest $data): Message
    {
        $message = $this->writer->build($exchange, $author, $data->text);
        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    private function createIdempotent(Exchange $exchange, \App\Entity\Membership $author, CreateMessageRequest $data, string $key): Message
    {
        return $this->em->wrapInTransaction(function () use ($exchange, $author, $data, $key): Message {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:k))',
                ['k' => 'message_idem:'.$exchange->getId().':'.$key],
            );

            $existing = $this->messages->findOneByIdempotencyKey($exchange, $key);
            if ($existing instanceof Message) {
                return $existing; // rejeu : aucun doublon
            }

            $message = $this->writer->build($exchange, $author, $data->text);
            $message->setIdempotencyKey($key);
            $this->em->persist($message);

            return $message;
        });
    }
}
