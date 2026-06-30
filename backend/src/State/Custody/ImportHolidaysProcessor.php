<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Enum\AuditAction;
use App\Exception\CustodyInputException;
use App\Message\ImportSchoolHolidays;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US-CUST-3 — déclenche l'import **asynchrone** des vacances officielles (202). Exige zone scolaire
 * et académie renseignées (réglages famille). Le job upsert + régénère (idempotent).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ImportHolidaysProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CustodyAccess $access,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $ctx = $this->access->write();
        $family = $ctx->family;

        if (null === $family->getSchoolZone() || null === $family->getAcademy()
            || '' === $family->getSchoolZone() || '' === $family->getAcademy()) {
            throw new CustodyInputException('custody.error.holidays_settings_required');
        }

        $request = $this->requestStack->getCurrentRequest();
        $schoolYear = $request?->query->get('schoolYear');
        $schoolYear = \is_string($schoolYear) && '' !== $schoolYear ? $schoolYear : null;

        $this->bus->dispatch(new ImportSchoolHolidays((string) $family->getId(), $schoolYear));

        $this->audit->log(
            AuditAction::CustodyHolidayImported,
            $ctx->user(),
            $family,
            ip: $request?->getClientIp(),
            metadata: ['schoolYear' => $schoolYear],
        );

        return null;
    }
}
