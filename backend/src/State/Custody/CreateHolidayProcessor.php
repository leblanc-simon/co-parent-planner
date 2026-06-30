<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\CreateHolidayRequest;
use App\Dto\Custody\SchoolHolidayResource;
use App\Entity\SchoolHoliday;
use App\Enum\AuditAction;
use App\Enum\HolidaySource;
use App\Exception\CustodyInputException;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * US-CUST-3 — crée une période de vacances manuelle (custody:edit, famille séparée).
 *
 * @implements ProcessorInterface<CreateHolidayRequest, SchoolHolidayResource>
 */
final class CreateHolidayProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolHolidayResource
    {
        \assert($data instanceof CreateHolidayRequest);

        $ctx = $this->access->write();
        $start = $this->parse($data->startDate);
        $end = $this->parse($data->endDate);
        if ($end < $start) {
            throw new CustodyInputException('custody.error.range_invalid');
        }

        $holiday = (new SchoolHoliday())
            ->setFamily($ctx->family)
            ->setLabel(trim((string) $data->label))
            ->setStartDate($start)
            ->setEndDate($end)
            ->setSource(HolidaySource::Manual);
        $this->em->persist($holiday);

        $this->audit->log(
            AuditAction::CustodyHolidayCreated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            entityType: 'SchoolHoliday',
            entityId: (string) $holiday->getId(),
        ); // flush

        return SchoolHolidayResource::fromHoliday($holiday);
    }

    private function parse(?string $raw): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
        if (false === $d || $d->format('Y-m-d') !== $raw) {
            throw new CustodyInputException('custody.error.date_invalid');
        }

        return $d;
    }
}
