<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Dto\Custody\CreateExceptionRequest;
use App\Entity\CustodyException;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\MembershipRole;
use App\Exception\CustodyInputException;
use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Valide et construit une `CustodyException` (US-CUST-4) : bornes cohérentes, plage dans l'horizon
 * (présent → +18 mois, pas de passé), parent cible = parent actif de la famille. 422 sinon.
 */
final class ExceptionWriter
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(Family $family, CreateExceptionRequest $data, ?Membership $createdBy): CustodyException
    {
        $start = $this->parse($data->startDate);
        $end = $this->parse($data->endDate);
        if ($end < $start) {
            throw new CustodyInputException('custody.error.range_invalid');
        }

        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
        $horizon = $today->modify('+18 months');
        if ($start < $today || $end > $horizon) {
            throw new CustodyInputException('custody.error.out_of_horizon');
        }

        return (new CustodyException())
            ->setFamily($family)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setParentMembership($this->parentMembership($family, (string) $data->parentMembershipId))
            ->setReason(null !== $data->reason ? trim($data->reason) : null)
            ->setCreatedBy($createdBy);
    }

    private function parse(?string $raw): \DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
        if (false === $d || $d->format('Y-m-d') !== $raw) {
            throw new CustodyInputException('custody.error.date_invalid');
        }

        return $d;
    }

    private function parentMembership(Family $family, string $id): Membership
    {
        if (!Uuid::isValid($id)) {
            throw new CustodyInputException('custody.error.exception_parent_invalid');
        }
        $m = $this->memberships->find(Uuid::fromString($id));
        if (!$m instanceof Membership
            || !$m->getFamily()->getId()->equals($family->getId())
            || MembershipRole::Parent !== $m->getRole()
            || !$m->isActive()) {
            throw new CustodyInputException('custody.error.exception_parent_invalid');
        }

        return $m;
    }
}
