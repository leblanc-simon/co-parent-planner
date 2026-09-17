<?php

declare(strict_types=1);

namespace App\Service\Custody;

use App\Dto\Custody\UpdatePatternRequest;
use App\Entity\CustodyPattern;
use App\Entity\Family;
use App\Entity\Membership;
use App\Enum\CustodyPatternKind;
use App\Enum\MembershipRole;
use App\Exception\CustodyInputException;
use App\Repository\MembershipRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Valide une requête de rythme (validation conditionnelle selon `kind`, §5.1) et l'applique à une
 * entité `CustodyPattern`. Lève `CustodyInputException` (422) en cas d'incohérence ; aucune écriture.
 */
final class PatternWriter
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly \Psr\Clock\ClockInterface $clock,
    ) {
    }

    public function apply(CustodyPattern $pattern, Family $family, UpdatePatternRequest $data): void
    {
        $kind = CustodyPatternKind::tryFrom((string) $data->kind);
        if (null === $kind) {
            throw new CustodyInputException('custody.error.kind_invalid');
        }
        $anchor = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data->anchorDate);
        if (false === $anchor || $anchor->format('Y-m-d') !== $data->anchorDate) {
            throw new CustodyInputException('custody.error.anchor_invalid');
        }
        // L'ancre référence un cycle déjà en cours : une ancre future laisserait des jours obsolètes
        // dans [aujourd'hui, ancre[ non régénérés (le passé/présent est figé à partir d'aujourd'hui).
        $today = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d');
        if ($anchor->format('Y-m-d') > $today) {
            throw new CustodyInputException('custody.error.anchor_future');
        }
        $weekday = $data->handoverWeekday ?? -1;
        if ($weekday < 0 || $weekday > 6) {
            throw new CustodyInputException('custody.error.weekday_invalid');
        }
        if (1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $data->handoverTime)) {
            throw new CustodyInputException('custody.error.handover_time_invalid');
        }
        // Heure de « début de journée » : facultative (le rythme existant garde la sienne, un
        // nouveau naît à 08:00), mais jamais illisible — elle sert de défaut aux échanges partiels.
        if (null !== $data->dayStartTime && 1 !== preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data->dayStartTime)) {
            throw new CustodyInputException('custody.error.day_start_time_invalid');
        }

        [$parentA, $parentB] = $this->resolveParents($family, $data);
        $this->validateConfig($kind, $data);

        $pattern->setKind($kind)
            ->setAnchorDate($anchor)
            ->setHandoverWeekday($weekday)
            ->setHandoverTime((string) $data->handoverTime)
            ->setDayStartTime($data->dayStartTime ?? $pattern->getDayStartTime())
            ->setParentA($parentA)
            ->setParentB($parentB)
            ->setCustomPattern(CustodyPatternKind::Custom === $kind ? array_values($data->customPattern ?? []) : null)
            ->setSettings(CustodyPatternKind::MidweekWeekends === $kind ? ($data->settings ?? []) : null);
    }

    /**
     * @return array{0: Membership, 1: Membership}
     */
    private function resolveParents(Family $family, UpdatePatternRequest $data): array
    {
        if (null === $data->parentAMembershipId || null === $data->parentBMembershipId
            || $data->parentAMembershipId === $data->parentBMembershipId) {
            throw new CustodyInputException('custody.error.parents_invalid');
        }
        $a = $this->parentMembership($family, $data->parentAMembershipId);
        $b = $this->parentMembership($family, $data->parentBMembershipId);

        return [$a, $b];
    }

    private function parentMembership(Family $family, string $id): Membership
    {
        if (!Uuid::isValid($id)) {
            throw new CustodyInputException('custody.error.parents_invalid');
        }
        $m = $this->memberships->find(Uuid::fromString($id));
        if (!$m instanceof Membership
            || !$m->getFamily()->getId()->equals($family->getId())
            || MembershipRole::Parent !== $m->getRole()
            || !$m->isActive()) {
            throw new CustodyInputException('custody.error.parents_invalid');
        }

        return $m;
    }

    private function validateConfig(CustodyPatternKind $kind, UpdatePatternRequest $data): void
    {
        $hasCustom = [] !== ($data->customPattern ?? []);
        $hasSettings = [] !== ($data->settings ?? []);

        if (CustodyPatternKind::Custom === $kind) {
            $this->validateCustom($data->customPattern ?? []);
            if ($hasSettings) {
                throw new CustodyInputException('custody.error.config_conflict');
            }

            return;
        }
        if (CustodyPatternKind::MidweekWeekends === $kind) {
            $this->validateSettings($data->settings ?? []);
            if ($hasCustom) {
                throw new CustodyInputException('custody.error.config_conflict');
            }

            return;
        }
        // alternating / pattern_2_2_3 : aucune config additionnelle.
        if ($hasCustom || $hasSettings) {
            throw new CustodyInputException('custody.error.config_conflict');
        }
    }

    /** @param list<string> $custom */
    private function validateCustom(array $custom): void
    {
        if (14 !== \count($custom)) {
            throw new CustodyInputException('custody.error.custom_invalid');
        }
        foreach ($custom as $slot) {
            if ('A' !== $slot && 'B' !== $slot) {
                throw new CustodyInputException('custody.error.custom_invalid');
            }
        }
    }

    /** @param array<string, mixed> $settings */
    private function validateSettings(array $settings): void
    {
        $weekendDays = array_map('intval', $settings['weekend_days'] ?? []);
        $weekdayParents = $settings['weekday_parents'] ?? null;
        $weekendStart = $settings['weekend_start_parent'] ?? null;

        if (\count($weekendDays) < 1 || \count($weekendDays) > 3 || \count($weekendDays) !== \count(array_unique($weekendDays))
            || !\is_array($weekdayParents)
            || !\in_array($weekendStart, ['A', 'B'], true)) {
            throw new CustodyInputException('custody.error.settings_invalid');
        }
        foreach ($weekendDays as $d) {
            if ($d < 0 || $d > 6) {
                throw new CustodyInputException('custody.error.settings_invalid');
            }
        }
        // Les jours de semaine non-week-end doivent être couverts exactement (aucun manquant/en trop).
        $expectedWeekdays = array_values(array_diff(range(0, 6), $weekendDays));
        $provided = array_map('intval', array_keys($weekdayParents));
        sort($provided);
        if ($expectedWeekdays !== $provided) {
            throw new CustodyInputException('custody.error.settings_invalid');
        }
        foreach ($weekdayParents as $slot) {
            if ('A' !== $slot && 'B' !== $slot) {
                throw new CustodyInputException('custody.error.settings_invalid');
            }
        }
    }
}
