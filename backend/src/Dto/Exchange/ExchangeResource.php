<?php

declare(strict_types=1);

namespace App\Dto\Exchange;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\Family\FamilyResource;
use App\Entity\Exchange;
use App\State\Exchange\CreateExchangeProcessor;
use App\State\Exchange\DecideProcessor;
use App\State\Exchange\ExchangeItemProvider;
use App\State\Exchange\ExchangesProvider;
use App\State\Exchange\MarkReadProcessor;
use App\State\Exchange\UpdateExchangeProcessor;

/**
 * Échange exposé (Lot 6 §6.1). La collection `/families/{id}/exchanges?status=` renvoie les résumés
 * (sans le fil) triés (§3.6) ; `GET /exchanges/{id}` renvoie le détail (options + décomptes + fil en
 * clair). `POST .../decide` valide une option et renvoie l'échange (statut + options à jour) ;
 * `POST .../read` marque le fil lu pour l'appelant (X5). Le
 * marqueur `overdue` est dérivé (fuseau famille, §3.4).
 */
#[ApiResource(
    shortName: 'Exchange',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/exchanges',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            parameters: [
                'status' => new QueryParameter(
                    description: 'Filtre de statut : `open` ou `decided`. Absent = tous les échanges de la famille.',
                    schema: ['type' => 'string', 'enum' => ['open', 'decided']],
                ),
            ],
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
        // Marque l'échange lu par l'appelant (X5). Sans corps ; idempotent — la lecture n'avance
        // jamais en arrière. `exchanges:view` suffit (cf. MarkReadProcessor).
        new Post(
            uriTemplate: '/exchanges/{id}/read',
            status: 200,
            input: false,
            read: false,
            processor: MarkReadProcessor::class,
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
    /**
     * Prénom de l'enfant concerné (X4). La carte de liste écrivait « Un enfant » — elle avait
     * l'identifiant mais pas le nom, et c'est le nom qui permet de trier d'un coup d'œil.
     */
    public ?string $childName = null;
    public ?string $authorMembershipId = null;
    public ?string $authorName = null;
    public ?string $authorColor = null;
    public ?string $deadline = null;
    public string $status = '';
    public ?string $decidedOptionId = null;
    public bool $overdue = false;
    public string $createdAt = '';
    public int $messageCount = 0;

    /**
     * État du tour de vote d'une **décision**, du point de vue de l'appelant (X1).
     *
     * L'écran affichait un bouton « Valider » par option, sans jamais dire ce que le clic engageait :
     * `DecisionEngine` ne tranche que lorsque **tous** les électeurs ont voté, si bien que le même
     * geste était tantôt un brouillon modifiable, tantôt une signature définitive — et les deux
     * situations étaient identiques à l'écran. Ces trois champs les distinguent.
     *
     * Volontairement **agrégés** : on dit qui n'a pas encore voté, jamais pour quoi chacun a voté —
     * un scrutin ouvert entre coparents n'est pas la même chose qu'un décompte.
     */
    public ?string $myVotedOptionId = null;
    /**
     * Noms des électeurs qui n'ont pas encore voté, **hors moi**.
     *
     * @var list<string>
     */
    public array $awaitingVoters = [];
    /**
     * Je suis le dernier électeur à ne pas avoir voté : mon vote **peut** trancher — « peut », car
     * une égalité laisse l'échange ouvert (§3.3).
     */
    public bool $myVoteMaySettle = false;
    public ?string $lastMessageAt = null;
    /**
     * L'échange a bougé depuis ma dernière lecture (X5).
     *
     * Rien ne distinguait un fil qui venait de recevoir trois messages d'un fil dormant depuis un
     * mois : il fallait ouvrir chacun pour le savoir. Comparaison de dates, du seul point de vue de
     * l'appelant — poster un message ou ouvrir un échange marque sa lecture, ce qui garde la règle
     * réduite à `dernière activité > dernière lecture`.
     */
    public bool $hasUnread = false;
    /**
     * Version de l'échange à renvoyer dans `expectedVersion` d'un PATCH (verrou optimiste, §4.2) —
     * même défaut que l'agenda : la contrainte existait côté serveur sans être lisible (C20).
     */
    public int $version = 1;

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
        $view->childName = $exchange->getChild()?->getFirstName();
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
        $view->version = $exchange->getVersion();

        return $view;
    }
}
