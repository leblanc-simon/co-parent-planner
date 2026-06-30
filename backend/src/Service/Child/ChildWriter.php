<?php

declare(strict_types=1);

namespace App\Service\Child;

use App\Dto\Child\CreateChildRequest;
use App\Dto\Child\UpdateChildRequest;
use App\Entity\Child;
use App\Exception\ChildInputException;
use Psr\Clock\ClockInterface;

/**
 * Applique les champs de profil enfant (création/édition), avec parsing/validation de la date de
 * naissance (pas dans le futur, année ≥ 1900) et normalisation des informations pratiques (§3.2).
 */
final class ChildWriter
{
    public function __construct(
        private readonly InfoNormalizer $info,
        private readonly ClockInterface $clock,
    ) {
    }

    public function applyCreate(Child $child, CreateChildRequest $data): void
    {
        \assert(null !== $data->firstName);
        $child->setFirstName(trim($data->firstName));
        if (null !== $data->color) {
            $child->setColor($data->color);
        }
        $child->setBirthDate($this->parseBirthDate($data->birthDate, $child));
        $child->setSchoolLabel($this->cleanLabel($data->schoolLabel));
        $child->setInfo($this->info->normalize($data->info ?? []));
    }

    /**
     * @param array<int, string> $provided clés présentes dans le corps (PATCH partiel)
     */
    public function applyUpdate(Child $child, UpdateChildRequest $data, array $provided): void
    {
        if (\in_array('firstName', $provided, true) && null !== $data->firstName) {
            $child->setFirstName(trim($data->firstName));
        }
        if (\in_array('color', $provided, true) && null !== $data->color) {
            $child->setColor($data->color);
        }
        if (\in_array('birthDate', $provided, true)) {
            $child->setBirthDate($this->parseBirthDate($data->birthDate, $child));
        }
        if (\in_array('schoolLabel', $provided, true)) {
            $child->setSchoolLabel($this->cleanLabel($data->schoolLabel));
        }
        if (\in_array('info', $provided, true)) {
            $child->setInfo($this->info->normalize($data->info ?? []));
        }
    }

    private function parseBirthDate(?string $raw, Child $child): ?\DateTimeImmutable
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw));
        if (false === $date || $date->format('Y-m-d') !== trim($raw)) {
            throw new ChildInputException('child.error.birth_date_invalid');
        }
        if ((int) $date->format('Y') < 1900) {
            throw new ChildInputException('child.error.birth_date_invalid');
        }

        $tz = new \DateTimeZone($child->getFamily()->getTimezone());
        $today = $this->clock->now()->setTimezone($tz)->format('Y-m-d');
        if ($date->format('Y-m-d') > $today) {
            throw new ChildInputException('child.error.birth_future');
        }

        return $date;
    }

    private function cleanLabel(?string $label): ?string
    {
        $label = null === $label ? null : trim($label);

        return '' === $label ? null : $label;
    }
}
