<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyDay;
use App\Service\Custody\DaySegments;
use App\State\Custody\CustodyDaysProvider;

/** Jour de garde effectif exposé (US-CUST-5). */
#[ApiResource(
    shortName: 'CustodyDay',
    operations: [
        new GetCollection(
            uriTemplate: '/families/{familyId}/custody/days',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            paginationEnabled: false,
            // Fenêtre déclarée au contrat (revue C3) : sans elle, aucun client généré ne pouvait
            // demander autre chose que le mois courant.
            parameters: [
                // `pattern` plutôt que `format: date` : le générateur Dart mapperait
                // `format: date` sur `DateTime` (ISO 8601 complet), que `CustodyRange` refuse.
                'from' => new QueryParameter(
                    description: 'Début de la fenêtre (date locale, AAAA-MM-JJ). Défaut : 1er du mois courant.',
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
                'to' => new QueryParameter(
                    description: 'Fin de la fenêtre, incluse (AAAA-MM-JJ). Défaut : dernier jour du mois courant.',
                    schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ),
            ],
            provider: CustodyDaysProvider::class,
        ),
    ],
)]
class CustodyDayResource
{
    public string $date = '';
    /** Parent **majoritaire** en heures : celui à qui la journée est comptée. */
    public ?string $parentMembershipId = null;
    public string $source = '';
    /**
     * Découpage horaire de la journée (cdc §3.4), toujours renseigné et couvrant `00:00` → `24:00` :
     * une tranche pour une journée unie, deux quand la transmission tombe dedans, trois quand un
     * échange partiel est encadré par le rythme. `parent` est le membership, `source` l'origine
     * (`base`/`holiday`/`exception`) de la tranche.
     *
     * Le client peint ce qu'on lui donne : la règle de transmission (« jusqu'à l'heure d'échange
     * chez le parent du jour, ensuite chez celui du lendemain ») vit dans le moteur, pas dans les
     * deux applications.
     *
     * @var list<array{from: string, to: string, parent: string|null, source: string}>
     */
    public array $segments = [];

    public static function fromDay(CustodyDay $d): self
    {
        $parentId = $d->getParentMembership() ? (string) $d->getParentMembership()->getId() : null;

        $dto = new self();
        $dto->date = $d->getDate()->format('Y-m-d');
        $dto->parentMembershipId = $parentId;
        $dto->source = $d->getSource()->value;
        $dto->segments = DaySegments::fromArray($d->getSegments(), $parentId, $d->getSource())->toArray();

        return $dto;
    }
}
