<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Put;
use App\Dto\Family\FamilyResource;
use App\Entity\CustodyPattern;
use App\State\Custody\CustodyPatternProvider;
use App\State\Custody\UpdatePatternProcessor;

/** Rythme de garde exposé (US-CUST-1). */
#[ApiResource(
    shortName: 'CustodyPattern',
    operations: [
        new Get(
            uriTemplate: '/families/{familyId}/custody/pattern',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            provider: CustodyPatternProvider::class,
        ),
        new Put(
            uriTemplate: '/families/{familyId}/custody/pattern',
            uriVariables: ['familyId' => new Link(fromClass: FamilyResource::class, identifiers: ['id'])],
            input: UpdatePatternRequest::class,
            read: false,
            processor: UpdatePatternProcessor::class,
        ),
    ],
)]
class CustodyPatternResource
{
    public string $id = '';
    public string $kind = '';
    public ?string $anchorDate = null;
    public int $handoverWeekday = 0;
    public string $handoverTime = '';
    /** Heure à laquelle « la journée commence » : défaut proposé aux échanges partiels. */
    public string $dayStartTime = '';
    /** @var list<string>|null */
    public ?array $customPattern = null;
    /**
     * Réglages du modèle `midweek_weekends` (US-CUST-1) : jours de week-end, parent de chaque jour
     * de semaine, parent qui ouvre l'alternance des week-ends.
     *
     * Déclaré **objet libre** parce qu'API Platform ne sait rien inférer d'un `array<string, mixed>`
     * et produisait `additionalProperties: {type: string}` — un objet plat de chaînes. Le client Dart
     * généré depuis ce contrat cassait donc à la **désérialisation** (`List<int> is not a subtype of
     * String?`) dès qu'une famille utilisait ce modèle (revue mobile 2026-08, constat C26). Décrire
     * les trois clés en `properties` corrigeait bien le typage, mais faisait générer trois classes
     * anonymes `Settings`/`Settings2`/`Settings3` — noms renumérotés au moindre objet inline ajouté
     * ailleurs, donc churn permanent du code généré. La forme réelle reste validée côté serveur par
     * {@see \App\Service\Custody\PatternWriter::validateSettings()} ; l'exemple ci-dessous la
     * documente au contrat.
     *
     * @var array{weekend_days?: list<int>, weekday_parents?: array<string, string>, weekend_start_parent?: string}|null
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'nullable' => true,
        'additionalProperties' => true,
        'example' => [
            'weekend_days' => [6, 0],
            'weekday_parents' => ['1' => 'A', '2' => 'A', '3' => 'B', '4' => 'B', '5' => 'B'],
            'weekend_start_parent' => 'A',
        ],
    ])]
    public ?array $settings = null;
    public ?string $parentAMembershipId = null;
    public ?string $parentBMembershipId = null;

    public static function fromPattern(CustodyPattern $p): self
    {
        $dto = new self();
        $dto->id = (string) $p->getId();
        $dto->kind = $p->getKind()->value;
        $dto->anchorDate = $p->getAnchorDate()->format('Y-m-d');
        $dto->handoverWeekday = $p->getHandoverWeekday();
        $dto->handoverTime = $p->getHandoverTime();
        $dto->dayStartTime = $p->getDayStartTime();
        $dto->customPattern = $p->getCustomPattern();
        $dto->settings = $p->getSettings();
        $dto->parentAMembershipId = $p->getParentA() ? (string) $p->getParentA()->getId() : null;
        $dto->parentBMembershipId = $p->getParentB() ? (string) $p->getParentB()->getId() : null;

        return $dto;
    }
}
