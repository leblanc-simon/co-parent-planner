<?php

declare(strict_types=1);

namespace App\Dto\Custody;

use ApiPlatform\Metadata\ApiProperty;

/**
 * Création/modification du rythme de garde (US-CUST-1/2/9). La validation conditionnelle (selon
 * `kind`) et la résolution des parents A/B sont faites par {@see \App\Service\Custody\PatternWriter}.
 */
final class UpdatePatternRequest
{
    public ?string $kind = null;
    public ?string $anchorDate = null;
    public ?int $handoverWeekday = null;
    public ?string $handoverTime = null;
    /** Heure de « début de journée » (HH:MM) ; omise = inchangée (08:00 à la création). */
    public ?string $dayStartTime = null;
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
}
