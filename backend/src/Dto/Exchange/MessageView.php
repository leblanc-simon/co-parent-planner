<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

use App\Entity\Message;

/**
 * Vue plate d'un message pour l'**imbrication** dans le détail d'un échange (évite la sérialisation
 * en IRI de {@see MessageResource}, qui reste la ressource du POST). `authorName`/`authorColor` sont
 * `null` pour un auteur anonymisé (§4.8).
 */
class MessageView
{
    public string $id = '';
    public ?string $authorMembershipId = null;
    public ?string $authorName = null;
    public ?string $authorColor = null;
    public string $text = '';
    public string $sentAt = '';

    public static function fromEntity(Message $message, \DateTimeZone $tz): self
    {
        $view = new self();
        $view->id = (string) $message->getId();
        $author = $message->getAuthorMembership();
        if (null !== $author) {
            $view->authorMembershipId = (string) $author->getId();
            $view->authorName = $author->getUser()->getFullName() ?: $author->getUser()->getEmail();
            $view->authorColor = $author->getDisplayColor();
        }
        $view->text = $message->getText();
        $view->sentAt = $message->getSentAt()->setTimezone($tz)->format(\DateTimeInterface::ATOM);

        return $view;
    }
}
