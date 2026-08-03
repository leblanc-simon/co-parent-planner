<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Family\FamilyResource;
use App\Entity\Exchange;
use App\State\Exchange\CreateExchangeProcessor;
use App\State\Exchange\DecideProcessor;
use App\State\Exchange\ExchangeItemProvider;
use App\State\Exchange\ExchangesProvider;
use App\State\Exchange\UpdateExchangeProcessor;

/**
 * Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés
 * (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en
 * clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour). Le
 * marqueur `overdue` est dérivé (fuseau famille, §3.4).
 */
#[ApiResource(
    shortName: 'Exchange',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/exchanges',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            provider: ExchangesProvider::class,
        ),
        new Post(
            uriTemplate: '/families/{familyId}/exchanges',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            status: 201,
            input: CreateExchangeRequest::class,
            read: false,
            processor: CreateExchangeProcessor::class,
        ),
        new Get(
            uriTemplate: '/exchanges/{id}',
            provider: ExchangeItemProvider::class,
        ),
        new Patch(
            uriTemplate: '/exchanges/{id}',
            input: UpdateExchangeRequest::class,
            read: false,
            processor: UpdateExchangeProcessor::class,
        ),
        new Post(
            uriTemplate: '/exchanges/{id}/decide',
            input: DecideRequest::class,
            read: false,
            processor: DecideProcessor::class,
        ),
    ],
)]
class ExchangeResource
{
    public string $id = '';
    public string $type = '';
    public string $title = '';
    public ?string $body = null;
    public ?string $childId = null;
    public ?string $authorMembershipId = null;
    public ?string $authorName = null;
    public ?string $authorColor = null;
    public ?string $deadline = null;
    public string $status = '';
    public ?string $decidedOptionId = null;
    public bool $overdue = false;
    public string $createdAt = '';
    public int $messageCount = 0;
    public ?string $lastMessageAt = null;

    /** @var list<OptionView> */
    public array $options = [];

    /** @var list<MessageView> Fil complet (détail) ; vide en liste. */
    public array $messages = [];

    public static function scalarFrom(Exchange $exchange, bool $overdue): self
    {
        $view = new self();
        $view->id = (string) $exchange->getId();
        $view->type = $exchange->getType()->value;
        $view->title = $exchange->getTitle();
        $view->body = $exchange->getBody();
        $view->childId = null !== $exchange->getChild() ? (string) $exchange->getChild()->getId() : null;
        $author = $exchange->getAuthorMembership();
        if (null !== $author) {
            $view->authorMembershipId = (string) $author->getId();
            $view->authorName = $author->getUser()->getFullName() ?: $author->getUser()->getEmail();
            $view->authorColor = $author->getDisplayColor();
        }
        $view->deadline = $exchange->getDeadline()?->format('Y-m-d');
        $view->status = $exchange->getStatus()->value;
        $view->decidedOptionId = null !== $exchange->getDecidedOption() ? (string) $exchange->getDecidedOption()->getId() : null;
        $view->overdue = $overdue;
        $view->createdAt = $exchange->getCreatedAt()->format(\DateTimeInterface::ATOM);

        return $view;
    }
}
