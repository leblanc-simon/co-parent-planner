<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Entity\Message;
use App\State\Exchange\CreateMessageProcessor;

/**
 * Message du fil (Lot 6 §6.1). `POST /exchanges/{exchangeId}/messages` crée un message (texte
 * chiffré au repos, renvoyé **en clair** à l'appelant autorisé). Sert aussi de vue imbriquée dans le
 * détail d'un échange. `authorName`/`authorColor` sont `null` pour un auteur anonymisé (§4.8).
 */
#[ApiResource(
    shortName: 'Message',
    operations: [
        new Post(
            uriTemplate: '/exchanges/{exchangeId}/messages',
            uriVariables: ['exchangeId' => new Link(fromClass: ExchangeResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateMessageRequest::class,
            read: false,
            processor: CreateMessageProcessor::class,
        ),
    ],
)]
class MessageResource
{
    public string $id = '';
    public string $exchangeId = '';
    public ?string $authorMembershipId = null;
    public ?string $authorName = null;
    public ?string $authorColor = null;
    public string $text = '';
    public string $sentAt = '';

    public static function fromEntity(Message $message, \DateTimeZone $tz): self
    {
        $view = new self();
        $view->id = (string) $message->getId();
        $view->exchangeId = (string) $message->getExchange()->getId();
        $author = $message->getAuthorMembership();
        if (null !== $author) {
            $view->authorMembershipId = (string) $author->getId();
            $view->authorName = $author->getUser()->getFullName() ?? $author->getUser()->getEmail();
            $view->authorColor = $author->getDisplayColor();
        }
        $view->text = $message->getText();
        $view->sentAt = $message->getSentAt()->setTimezone($tz)->format(\DateTimeInterface::ATOM);

        return $view;
    }
}
