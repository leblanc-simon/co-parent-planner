<?php

declare(strict_types=1);

namespace App\State\Family;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Family\FamilyResource;
use App\Dto\Family\UpdateFamilyRequest;
use App\Entity\Family;
use App\Enum\AuditAction;
use App\Enum\FamilyType;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Security\Auth\AuditLogger;
use App\Security\FamilyScope;
use App\State\Concern\ReadsProvidedKeys;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-SET-2a — édite les réglages de la famille active (réservé `family_settings:edit`, donc admin).
 * PATCH partiel : seules les clés présentes dans le corps sont appliquées.
 *
 * @implements ProcessorInterface<UpdateFamilyRequest, FamilyResource>
 */
final class UpdateFamilyProcessor implements ProcessorInterface
{
    use ReadsProvidedKeys;

    public function __construct(
        private readonly FamilyScope $scope,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FamilyResource
    {
        \assert($data instanceof UpdateFamilyRequest);

        $ctx = $this->scope->require();
        $this->scope->denyUnless($ctx, RightDomain::FamilySettings, RightLevel::Edit);

        $family = $ctx->family;
        $provided = $this->providedKeys();

        $this->applyScalars($family, $data, $provided);
        $this->applyHandover($family, $data, $provided);

        $this->audit->log(
            AuditAction::FamilySettingsUpdated,
            $ctx->user(),
            $family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['fields' => array_values($provided)],
            entityType: 'Family',
            entityId: (string) $family->getId(),
        ); // flush

        return FamilyResource::fromMembership($ctx->membership);
    }

    protected function requestStack(): RequestStack
    {
        return $this->requestStack;
    }

    /**
     * @param array<int, string> $provided
     */
    private function applyScalars(Family $family, UpdateFamilyRequest $data, array $provided): void
    {
        if (\in_array('name', $provided, true) && null !== $data->name) {
            $family->setName(trim($data->name));
        }
        if (\in_array('type', $provided, true) && null !== $data->type) {
            $family->setType(FamilyType::from($data->type));
        }
        if (\in_array('timezone', $provided, true) && null !== $data->timezone) {
            $family->setTimezone($data->timezone);
        }
        if (\in_array('schoolZone', $provided, true)) {
            $family->setSchoolZone($data->schoolZone);
        }
        if (\in_array('academy', $provided, true)) {
            $family->setAcademy($data->academy);
        }
        if (\in_array('expensesEnabled', $provided, true) && null !== $data->expensesEnabled) {
            $family->setExpensesEnabled($data->expensesEnabled);
        }
    }

    /**
     * Jour/heure d'échange de garde stockés dans `settings` (jsonb), non interprétés au Lot 1.
     *
     * @param array<int, string> $provided
     */
    private function applyHandover(Family $family, UpdateFamilyRequest $data, array $provided): void
    {
        $settings = $family->getSettings();
        if (\in_array('handoverWeekday', $provided, true)) {
            $settings['handover_weekday'] = $data->handoverWeekday;
        }
        if (\in_array('handoverTime', $provided, true)) {
            $settings['handover_time'] = $data->handoverTime;
        }
        $family->setSettings($settings);
    }
}
