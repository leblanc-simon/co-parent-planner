<?php

declare(strict_types=1);

namespace App\State\Custody;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Custody\SchoolHolidayResource;
use App\Dto\Custody\UpdateRuleRequest;
use App\Entity\HolidayRule;
use App\Entity\SchoolHoliday;
use App\Enum\AuditAction;
use App\Enum\HolidayRuleKind;
use App\Exception\CustodyInputException;
use App\Exception\CustodyNotFoundException;
use App\Message\RegenerateCustody;
use App\Repository\SchoolHolidayRepository;
use App\Security\Auth\AuditLogger;
use App\Security\Custody\CustodyAccess;
use App\Service\Custody\RuleValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * US-CUST-3 — définit/met à jour la règle d'une période (custody:edit). Régénère le futur (async)
 * pour réécrire les jours de la période en `source=holiday` (sans écraser les exceptions).
 *
 * @implements ProcessorInterface<UpdateRuleRequest, SchoolHolidayResource>
 */
final class UpdateRuleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustodyAccess $access,
        private readonly SchoolHolidayRepository $holidays,
        private readonly RuleValidator $validator,
        private readonly MessageBusInterface $bus,
        private readonly AuditLogger $audit,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SchoolHolidayResource
    {
        \assert($data instanceof UpdateRuleRequest);

        $ctx = $this->access->write();
        $holiday = $this->load($uriVariables['id'] ?? null, $ctx->family);

        $kind = HolidayRuleKind::tryFrom((string) $data->kind);
        if (null === $kind) {
            throw new CustodyInputException('custody.error.rule_kind_invalid');
        }
        $params = $data->params ?? [];
        $this->validator->validate($kind, $params);

        $rule = $holiday->getRule() ?? new HolidayRule();
        $rule->setKind($kind)->setParams($params);
        $holiday->setRule($rule);
        if (!$this->em->contains($rule)) {
            $this->em->persist($rule);
        }

        $this->audit->log(
            AuditAction::CustodyRuleUpdated,
            $ctx->user(),
            $ctx->family,
            ip: $this->requestStack->getCurrentRequest()?->getClientIp(),
            metadata: ['holiday' => (string) $holiday->getId(), 'kind' => $kind->value],
            entityType: 'SchoolHoliday',
            entityId: (string) $holiday->getId(),
        ); // flush

        $this->bus->dispatch(new RegenerateCustody((string) $ctx->family->getId()));

        return SchoolHolidayResource::fromHoliday($holiday);
    }

    private function load(mixed $id, \App\Entity\Family $family): SchoolHoliday
    {
        if (!\is_string($id) || !Uuid::isValid($id)) {
            throw new CustodyNotFoundException();
        }
        $holiday = $this->holidays->find(Uuid::fromString($id));
        if (!$holiday instanceof SchoolHoliday || !$holiday->getFamily()->getId()->equals($family->getId())) {
            throw new CustodyNotFoundException();
        }

        return $holiday;
    }
}
