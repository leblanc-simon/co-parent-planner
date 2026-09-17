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

        // Bornes horaires (cdc §3.4) : facultatives — sans elles l'exception couvre des journées
        // entières, comme avant qu'elles existent. Avec elles, l'instant de fin doit suivre celui
        // de début, sinon l'exception ne couvre rien et l'échange serait un geste sans effet.
        $startTime = $this->parseTime($data->startTime);
        $endTime = $this->parseTime($data->endTime);
        if ($end->format('Y-m-d') === $start->format('Y-m-d')
            && (DaySegment::minutesOf($endTime) ?? DaySegment::MINUTES_PER_DAY) <= (DaySegment::minutesOf($startTime) ?? 0)) {
            throw new CustodyInputException('custody.error.range_invalid');
        }

        return (new CustodyException())
            ->setFamily($family)
            ->setStartDate($start)
            ->setEndDate($end)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setParentMembership($this->parentMembership($family, (string) $data->parentMembershipId))
            ->setReason(null !== $data->reason ? trim($data->reason) : null)
            ->setCreatedBy($createdBy);
    }

    /** `HH:MM` valide, ou `null` si l'heure est absente ; une heure illisible est un refus (422). */
    private function parseTime(?string $raw): ?string
    {
        if (null === $raw || '' === $raw) {
            return null;
        }
        if (1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw)) {
            throw new CustodyInputException('custody.error.time_invalid');
        }

        return $raw;
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
